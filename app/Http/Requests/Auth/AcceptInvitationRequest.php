<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existingActivePersonal = User::where('email', $this->input('email'))
            ->where('is_active', true)
            ->where('account_type', 'personal')
            ->exists();

        // Personal users already registered don't need to set a password
        if ($existingActivePersonal) {
            return [
                'token' => ['required', 'string', 'size:64'],
                'email' => ['required', 'email'],
            ];
        }

        return [
            'token'    => ['required', 'string', 'size:64'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
