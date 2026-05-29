<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('credential'));
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'max:5000'],
            'url'      => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type'     => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
            'image_id' => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ];
    }
}
