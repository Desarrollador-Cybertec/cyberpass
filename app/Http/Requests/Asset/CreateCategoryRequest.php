<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // La division tiene que pertenecer a la misma organizacion en la
            // que se esta creando la categoria: antes bastaba con que la
            // division existiera en CUALQUIER organizacion.
            'division_id' => [
                'sometimes',
                'nullable',
                Rule::exists('divisions', 'id')->where('organization_id', $this->route('organization')?->id),
            ],
            'image_id'    => ['sometimes', 'nullable', 'integer', 'exists:images,id'],
            'replicate_to_other_organizations' => ['sometimes', 'boolean'],
        ];
    }
}
