<?php

namespace App\Http\Requests\Results;

use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;

class JobTitlesRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'governorate' => ['required', 'string', 'max:100'],
        ];
    }
}
