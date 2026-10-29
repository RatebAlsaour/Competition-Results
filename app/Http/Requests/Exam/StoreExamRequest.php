<?php

namespace App\Http\Requests\Exam;

use App\Enum\CompetitionStatusEnum;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'slug'              => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'instructions'      => ['nullable', 'string', 'max:5000'],
            'status'            => ['nullable', Rule::in(CompetitionStatusEnum::values())],
            'results_published' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title'        => 'اسم الامتحان',
            'slug'         => 'الرابط المختصر',
            'description'  => 'الوصف',
            'instructions' => 'التعليمات',
            'status'       => 'الحالة',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'الرابط المختصر يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً وشرطة (-) فقط',
        ];
    }
}
