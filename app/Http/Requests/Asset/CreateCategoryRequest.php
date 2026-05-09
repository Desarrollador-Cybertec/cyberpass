<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            \App\Models\Category::class,
            $this->route('organization'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'division_id' => ['sometimes', 'nullable', 'integer', 'exists:divisions,id'],
            'image_id'    => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
        ];
    }
}
