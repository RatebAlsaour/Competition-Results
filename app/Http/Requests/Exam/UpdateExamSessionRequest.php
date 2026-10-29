<?php

namespace App\Http\Requests\Exam;

use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSessionRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:100'],
            'exam_date'  => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time'   => ['nullable', 'date_format:H:i'],
            'location'   => ['nullable', 'string', 'max:255'],
            'notes'      => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'       => 'اللغة',
            'exam_date'  => 'تاريخ الامتحان',
            'start_time' => 'وقت البدء',
            'end_time'   => 'وقت الانتهاء',
            'location'   => 'مكان الامتحان',
            'notes'      => 'الملاحظات',
        ];
    }
}
