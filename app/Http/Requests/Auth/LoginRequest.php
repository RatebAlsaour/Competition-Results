<?php

namespace App\Http\Requests\Auth;

use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'email'    => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
        ];
    }
}
