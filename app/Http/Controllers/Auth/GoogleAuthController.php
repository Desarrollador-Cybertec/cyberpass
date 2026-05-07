<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private AuditService $audit,
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(): JsonResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $result = $this->auth->findOrCreateFromGoogle($googleUser);

        if (! $result['requires_2fa_setup']) {
            $this->audit->log($result['user'], 'login');
        }

        return response()->json([
            'user'               => new UserResource($result['user']),
            'token'              => $result['token'],
            'requires_2fa_setup' => $result['requires_2fa_setup'],
        ]);
    }
}
