<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        if ($user?->two_factor_enabled) {
            return [
                'otp'      => ['required', 'string', 'digits:6'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ];
        }

        return [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required'             => 'El código OTP es requerido.',
            'otp.digits'               => 'El código OTP debe tener 6 dígitos.',
            'current_password.required' => 'La contraseña actual es requerida.',
        ];
    }
}
