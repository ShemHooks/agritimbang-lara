<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateLguEncoderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $encoderId = $this->route('encoder');

        return [
            'first_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'middle_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'last_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'sometimes',
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($encoderId),
            ],

            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                \App\Support\ContactValidationRules::philippineMobile(),
                'max:13',
            ],

            'password' => [
                'sometimes',
                'nullable',
                'confirmed',
                Password::min(8),
            ],

            'station' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'designation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }
    }
}