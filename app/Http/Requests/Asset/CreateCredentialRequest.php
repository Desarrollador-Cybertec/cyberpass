<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            \App\Models\Credential::class,
            $this->route('category'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:5000'],
            'notes'    => ['sometimes', 'nullable', 'string', 'max:5000'],
            'type'     => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
        ];
    }
}
