<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private AuditService $audit,
    ) {}

    public function redirect(): JsonResponse
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
        $provider = Socialite::driver('google');

        $url = $provider
            ->scopes(['openid', 'profile', 'email'])
            ->with(['access_type' => 'online'])
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function callback(): JsonResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\GoogleProvider $provider */
            $provider = Socialite::driver('google');
            $googleUser = $provider->stateless()->user();
        } catch (InvalidStateException) {
            return api_error('Estado OAuth inválido. Inicia el flujo de nuevo.', 422);
        }

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
