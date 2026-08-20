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
            'name'              => ['required', 'string', 'max:255'],
            'username'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'nextcloud_account' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'          => ['required', 'string', 'max:5000'],
            'url'               => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type'              => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other', 'email', 'nextcloud', 'email_nextcloud'])],
            'image_id'          => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ];
    }
}
