<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class ImportCredentialsCommitRequest extends FormRequest
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
            'import_token' => ['required', 'string'],
        ];
    }
}
