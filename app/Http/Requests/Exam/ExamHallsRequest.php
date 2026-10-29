<?php

namespace App\Http\Requests\Exam;

use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Either generate halls automatically (capacity) or replace them with an explicit list (halls).
 */
class ExamHallsRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capacity'            => ['required_without:halls', 'nullable', 'integer', 'min:1', 'max:5000'],
            'prefix'              => ['nullable', 'string', 'max:50'],
            'start_at'            => ['nullable', 'integer', 'min:1'],
            'halls'               => ['required_without:capacity', 'nullable', 'array', 'max:500'],
            'halls.*.name'        => ['required', 'string', 'max:100'],
            'halls.*.building'    => ['nullable', 'string', 'max:100'],
            'halls.*.from_number' => ['required', 'integer', 'min:1'],
            'halls.*.to_number'   => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'capacity'            => 'عدد المتقدمين في القاعة',
            'halls.*.name'        => 'اسم القاعة',
            'halls.*.building'    => 'البناء',
            'halls.*.from_number' => 'من رقم',
            'halls.*.to_number'   => 'إلى رقم',
        ];
    }
}
