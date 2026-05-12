<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateSharedTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            \App\Models\SharedAccessToken::class,
            $this->route('credential'),
        ]);
    }

    public function rules(): array
    {
        return [
            'expires_at'       => ['required_without:expires_in', 'nullable', 'date', 'after:now'],
            'expires_in'       => ['required_without:expires_at', 'nullable', 'array'],
            'expires_in.unit'  => ['required_with:expires_in', 'string', 'in:minutes,hours'],
            'expires_in.value' => ['required_with:expires_in', 'integer', 'min:1'],
            'max_uses'         => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'pin'              => ['sometimes', 'nullable', 'string', 'min:4', 'max:16'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->has('expires_at') && $this->has('expires_in')) {
                $v->errors()->add('expires_at', 'No puedes enviar expires_at y expires_in al mismo tiempo.');
                return;
            }

            if ($this->has('expires_in')) {
                $unit  = $this->input('expires_in.unit');
                $value = (int) $this->input('expires_in.value');

                if ($unit === 'minutes' && ($value < 1 || $value > 60)) {
                    $v->errors()->add('expires_in.value', 'Para minutos el valor debe estar entre 1 y 60.');
                }

                if ($unit === 'hours' && ($value < 1 || $value > 12)) {
                    $v->errors()->add('expires_in.value', 'Para horas el valor debe estar entre 1 y 12.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'expires_at.required_without' => 'Debes indicar una fecha de expiración o un tiempo relativo.',
            'expires_in.required_without' => 'Debes indicar una fecha de expiración o un tiempo relativo.',
            'expires_at.after'            => 'La fecha de expiración debe ser posterior a ahora.',
            'expires_in.unit.in'          => 'La unidad debe ser "minutes" u "hours".',
            'expires_in.value.min'        => 'El valor debe ser al menos 1.',
        ];
    }
}
