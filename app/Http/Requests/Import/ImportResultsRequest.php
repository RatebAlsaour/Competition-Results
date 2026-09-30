<?php

namespace App\Http\Requests\Import;

use App\Http\Services\ResultsImportService;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportResultsRequest extends FormRequest
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
            'mode' => ['nullable', Rule::in([ResultsImportService::MODE_REPLACE, ResultsImportService::MODE_APPEND])],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'ملف الإكسل',
            'mode' => 'طريقة الاستيراد',
        ];
    }
}
