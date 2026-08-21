<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class CreateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDivisions', $this->route('organization'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active'   => ['sometimes', 'boolean'],
            'image_id'    => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
            'replicate_to_other_organizations' => ['sometimes', 'boolean'],
        ];
    }
}
