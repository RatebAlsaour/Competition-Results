<?php

namespace App\Http\Requests\Competition;

use App\Enum\CompetitionStatusEnum;
use App\Enum\RankingMethodEnum;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetitionRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                      => ['required', 'string', 'max:255'],
            'slug'                       => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'description'                => ['nullable', 'string', 'max:2000'],
            'status'                     => ['nullable', Rule::in(CompetitionStatusEnum::values())],
            'ranking_method'             => ['nullable', Rule::in(RankingMethodEnum::values())],
            'required_documents'         => ['nullable', 'array', 'max:50'],
            'required_documents.*.title' => ['nullable', 'string', 'max:255'],
            'required_documents.*.notes' => ['nullable', 'array', 'max:10'],
            'required_documents.*.notes.*' => ['nullable', 'string', 'max:255'],
            'primary_note'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title'          => 'اسم المسابقة',
            'slug'           => 'الرابط المختصر',
            'description'    => 'الوصف',
            'status'         => 'الحالة',
            'ranking_method' => 'طريقة الترتيب',
            'primary_note'   => 'ملاحظة المقبولين الأساسيين',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'الرابط المختصر يجب أن يحتوي أحرفاً إنجليزية صغيرة وأرقاماً و"-" فقط',
        ];
    }
}
