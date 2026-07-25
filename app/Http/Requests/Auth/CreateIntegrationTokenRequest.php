<?php

namespace App\Http\Requests\Auth;

use App\Helpers\IntegrationAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateIntegrationTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'abilities'   => ['sometimes', 'array', 'min:1'],
            // Lista blanca estricta: nunca '*'. PersonalAccessToken::can()
            // cortocircuita con el comodín, así que un token así pasaría
            // cualquier comprobación de permisos.
            'abilities.*' => [Rule::in(IntegrationAbility::all())],
            'expires_in_days' => [
                'sometimes', 'integer', 'min:1',
                'max:'.config('integrations.token_max_days', 730),
            ],
            // Un token de integración es un secreto de larga vida: emitirlo
            // exige el segundo factor en vivo, igual que cambiar la contraseña.
            'otp' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required'     => 'El código OTP es requerido.',
            'otp.digits'       => 'El código OTP debe tener 6 dígitos.',
            'abilities.*.in'   => 'Uno de los permisos solicitados no existe.',
            'name.required'    => 'Ponle un nombre al token para reconocerlo después.',
        ];
    }
}
