<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'max:5000'],
            'url'      => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type'     => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ];
    }
}
