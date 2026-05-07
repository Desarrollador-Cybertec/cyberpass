<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\Verify2faRequest;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private AuditService $audit,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->validated());

        return response()->json([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->attemptLogin(
            $request->validated('email'),
            $request->validated('password'),
        );

        if ($result['requires_2fa'] ?? false) {
            return response()->json([
                'requires_2fa' => true,
                'temp_token'   => $result['temp_token'],
            ]);
        }

        $this->audit->log($result['user'], 'login');

        return response()->json([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function verify2fa(Verify2faRequest $request): JsonResponse
    {
        $result = $this->auth->completeTwoFactorLogin(
            $request->validated('temp_token'),
            $request->validated('otp'),
        );

        $this->audit->log($result['user'], '2fa_verified');
        $this->audit->log($result['user'], 'login');

        return response()->json([
            'user'  => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log($request->user(), 'logout');
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            new UserResource($request->user()->load('organization'))
        );
    }
}
