<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_code' => ['required', 'string', 'regex:/^BUY-[A-Z0-9]{6}$/i'],
        ];
    }
}
