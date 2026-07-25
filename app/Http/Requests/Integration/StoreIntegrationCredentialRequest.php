<?php

namespace App\Http\Requests\Integration;

use App\Services\IntegrationScopeResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntegrationCredentialRequest extends FormRequest
{
    /** La autorización la resuelve IntegrationScopeResolver, que responde 404/422. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Obligatorio y no derivado: si Axis se equivoca de categoría, esto
            // devuelve 422 en lugar de escribir el secreto en el ámbito erróneo.
            'scope'       => ['required', Rule::in([
                IntegrationScopeResolver::SCOPE_PERSONAL,
                IntegrationScopeResolver::SCOPE_ORGANIZATION,
            ])],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'username'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'password'    => ['required', 'string', 'max:5000'],
            'url'         => ['sometimes', 'nullable', 'url', 'max:2048'],
            'type'        => ['sometimes', Rule::in(['password', 'api_key', 'ssh', 'certificate', 'other'])],
            'image_id'    => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ];
    }
}
