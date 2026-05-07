<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDomain;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(): JsonResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $domain = substr(strrchr($googleUser->getEmail(), '@'), 1);
        $orgDomain = OrganizationDomain::where('domain', $domain)->where('is_verified', true)->first();

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'organization_id' => $orgDomain?->organization_id,
                'account_type' => $orgDomain ? 'enterprise' : 'personal',
                'role' => 'org_user',
                'last_login_at' => now(),
            ]
        );

        // Enterprise users must set up 2FA before accessing the system
        if ($user->isEnterprise() && ! $user->two_factor_enabled) {
            $token = $user->createToken('api')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'requires_2fa_setup' => true,
                'message' => 'Debes configurar 2FA para continuar.',
            ]);
        }

        $this->audit->log($user, 'login');
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
