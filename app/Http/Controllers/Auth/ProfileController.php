<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private Google2FA $google2fa,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Enterprise users must keep email on their organization's verified domains
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $verifiedDomains = $user->organization?->domains()
                ->where('is_verified', true)
                ->pluck('domain');

            if ($verifiedDomains && $verifiedDomains->isNotEmpty()) {
                $newEmailDomain = substr(strrchr($data['email'], '@'), 1);
                if (! $verifiedDomains->contains($newEmailDomain)) {
                    return response()->json([
                        'message' => 'El nuevo email debe pertenecer a un dominio verificado de tu organización.',
                    ], 422);
                }
            }
        }

        $user->update($data);

        $this->audit->log($user, 'update', User::class, $user->id, ['fields' => array_keys($data)]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->two_factor_enabled) {
            if (! $this->google2fa->verifyKey($user->two_factor_secret, $data['otp'])) {
                throw ValidationException::withMessages(['otp' => ['Código OTP incorrecto.']]);
            }
        } else {
            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => 'La contraseña actual es incorrecta.'], 422);
            }
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Revoke all other Sanctum tokens, keep the current one active
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        $this->audit->log($user, 'password_changed', User::class, $user->id);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== 'personal') {
            return response()->json(['message' => 'Solo las cuentas personales pueden eliminarse desde aquí.'], 403);
        }

        $user->tokens()->delete();

        $this->audit->log($user, 'delete', User::class, $user->id);

        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada correctamente.']);
    }
}
