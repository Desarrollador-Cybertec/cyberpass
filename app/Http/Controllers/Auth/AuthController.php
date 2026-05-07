<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDomain;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_type' => 'personal',
            'role' => 'org_user',
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Cuenta desactivada.'], 403);
        }

        // Detect enterprise by domain
        $domain = substr(strrchr($user->email, '@'), 1);
        $orgDomain = OrganizationDomain::where('domain', $domain)->where('is_verified', true)->first();

        if ($orgDomain && $user->account_type !== 'sysadmin') {
            // Sync organization if not set
            if (! $user->organization_id) {
                $user->update([
                    'organization_id' => $orgDomain->organization_id,
                    'account_type' => 'enterprise',
                ]);
            }
            $user->refresh();
        }

        // Enterprise users with 2FA enabled must complete TOTP step
        if ($user->isEnterprise() && $user->two_factor_enabled) {
            $tempToken = Str::random(64);
            Cache::put("2fa_pending:{$tempToken}", $user->id, now()->addMinutes(5));

            return response()->json([
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ]);
        }

        $user->update(['last_login_at' => now()]);
        $this->audit->log($user, 'login');

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function verify2fa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'temp_token' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = Cache::get("2fa_pending:{$data['temp_token']}");

        if (! $userId) {
            return response()->json(['message' => 'Token temporal inválido o expirado.'], 401);
        }

        $user = User::findOrFail($userId);

        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        $valid = $google2fa->verifyKey($user->two_factor_secret, $data['otp']);

        if (! $valid) {
            return response()->json(['message' => 'Código OTP incorrecto.'], 422);
        }

        Cache::forget("2fa_pending:{$data['temp_token']}");

        $user->update(['last_login_at' => now()]);
        $this->audit->log($user, '2fa_verified');
        $this->audit->log($user, 'login');

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
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
        return response()->json($request->user()->load('organization'));
    }
}
