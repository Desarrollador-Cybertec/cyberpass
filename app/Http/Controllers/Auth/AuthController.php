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
use Illuminate\Validation\ValidationException;

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
            'requires_2fa_setup' => true,
            'registration_token' => $result['registration_token'],
            'user'               => $result['pending_user'],
        ], 202)->cookie($this->makeRegistrationCookie($result['registration_token']));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->attemptLogin(
                $request->validated('email'),
                $request->validated('password'),
            );
        } catch (ValidationException $e) {
            rescue(fn () => $this->audit->log(null, 'login_failed', metadata: [
                'email' => $request->validated('email'),
            ]));
            throw $e;
        }

        if ($result['session_active'] ?? false) {
            return $this->activeSessionResponse($result);
        }

        if ($result['requires_2fa'] ?? false) {
            return response()->json([
                'requires_2fa' => true,
                'temp_token'   => $result['temp_token'],
            ]);
        }

        $this->audit->log($result['user'], 'login');

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 20));

        return response()->json([
            'user'             => new UserResource($result['user']),
            'token'            => $result['token'],
            'token_expires_at' => $expiresAt->toIso8601String(),
        ])->cookie($this->makeTokenCookie($result['token']));
    }

    public function verify2fa(Verify2faRequest $request): JsonResponse
    {
        try {
            $result = $this->auth->completeTwoFactorLogin(
                $request->validated('temp_token'),
                $request->validated('otp'),
            );
        } catch (ValidationException $e) {
            rescue(fn () => $this->audit->log(null, '2fa_failed'));
            throw $e;
        }

        if ($result['session_active'] ?? false) {
            return $this->activeSessionResponse($result);
        }

        $this->audit->log($result['user'], '2fa_verified');
        $this->audit->log($result['user'], 'login');

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 20));

        return response()->json([
            'user'             => new UserResource($result['user']),
            'token'            => $result['token'],
            'token_expires_at' => $expiresAt->toIso8601String(),
        ])->cookie($this->makeTokenCookie($result['token']));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log($request->user(), 'logout');
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.'])
            ->withoutCookie('access_token');
    }

    private function activeSessionResponse(array $result): JsonResponse
    {
        $this->audit->log($result['user'], 'login_failed', metadata: [
            'reason' => 'active_session',
        ]);

        return response()->json([
            'message'            => 'Ya existe una sesión activa para este usuario.',
            'session_active'     => true,
            'user'               => new UserResource($result['user']),
            'session_expires_at' => $result['session_expires_at'],
        ], 409);
    }

    private function makeTokenCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        $minutes = max(1, (int) config('sanctum.expiration', 5));

        return cookie(
            'access_token',
            $token,
            $minutes,
            '/',
            null,
            true,         // secure
            true,         // httpOnly
            false,
            'Strict'      // sameSite
        );
    }

    private function makeRegistrationCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            'registration_token',
            $token,
            10,
            '/',
            null,
            true,
            true,
            false,
            'Strict'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            new UserResource($request->user()->load('organization'))
        );
    }
}
