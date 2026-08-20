<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class ImportCredentialsPreviewRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
