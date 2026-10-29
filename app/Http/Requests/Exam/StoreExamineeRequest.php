<?php

namespace App\Http\Requests\Exam;

use App\Enum\ExamResultEnum;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamineeRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'       => ['required', 'string', 'max:255'],
            'exam_session_id' => ['required', 'integer', 'exists:exam_sessions,id'],
            'exam_number'     => ['required', 'integer', 'min:1'],
            'file_number'     => ['nullable', 'string', 'max:50'],
            'hall_name'       => ['nullable', 'string', 'max:100'],
            'admin_note'      => ['nullable', 'string', 'max:2000'],
            'result'          => ['nullable', Rule::in(ExamResultEnum::values())],
            'score'           => ['nullable', 'numeric', 'between:0,9999'],
            'result_note'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name'       => 'الاسم الثلاثي',
            'exam_session_id' => 'اللغة',
            'exam_number'     => 'الرقم الامتحاني',
            'file_number'     => 'رقم الملف',
            'hall_name'       => 'القاعة الخاصة',
            'admin_note'      => 'الملاحظة الداخلية',
            'result'          => 'النتيجة',
            'score'           => 'العلامة',
            'result_note'     => 'ملاحظة النتيجة',
        ];
    }
}
