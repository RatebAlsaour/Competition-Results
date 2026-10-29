<?php

namespace App\Http\Requests\Exam;

use App\Http\Services\ExamImportService;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportExamRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:' . config('results.max_upload_kb')],
            'type' => ['nullable', Rule::in([ExamImportService::TYPE_EXAMINEES, ExamImportService::TYPE_RESULTS])],
            'mode' => ['nullable', Rule::in([ExamImportService::MODE_REPLACE, ExamImportService::MODE_APPEND, ExamImportService::MODE_UPSERT])],
            'halls_session' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'ملف الإكسل',
            'type' => 'نوع الاستيراد',
            'mode' => 'طريقة الاستيراد',
            'halls_session' => 'لغة توزيع القاعات',
        ];
    }
}
