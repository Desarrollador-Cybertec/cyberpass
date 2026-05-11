<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSharedTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            \App\Models\SharedAccessToken::class,
            $this->route('credential'),
        ]);
    }

    public function rules(): array
    {
        return [
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'max_uses'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'pin'        => ['sometimes', 'nullable', 'string', 'min:4', 'max:16'],
        ];
    }
}
