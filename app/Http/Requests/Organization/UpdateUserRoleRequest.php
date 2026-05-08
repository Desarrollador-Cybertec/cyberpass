<?php

namespace App\Http\Requests\Organization;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageUsers', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'role'      => ['sometimes', 'in:org_admin,org_user'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
