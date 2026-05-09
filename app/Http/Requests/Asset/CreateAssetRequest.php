<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class CreateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subcategory = $this->route('subcategory');

        return $this->user()->can('update', $subcategory->category);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'metadata'    => ['sometimes', 'nullable', 'array'],
        ];
    }
}
