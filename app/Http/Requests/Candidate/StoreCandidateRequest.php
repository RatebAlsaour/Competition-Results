<?php

namespace App\Http\Requests\Candidate;

use App\Enum\CandidateStatusEnum;
use App\Enum\FollowUpStatusEnum;
use App\Traits\CustomFormRequestFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCandidateRequest extends FormRequest
{
    use CustomFormRequestFailed;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'        => ['required', 'string', 'max:255'],
            'governorate'      => ['required', 'string', 'max:100'],
            'job_title'        => ['required', 'string', 'max:150'],
            'status'           => ['required', Rule::in(CandidateStatusEnum::values())],
            'score'            => ['nullable', 'numeric', 'between:0,999'],
            'interview_date'   => ['nullable', 'date'],
            'follow_up_status' => ['nullable', Rule::in(FollowUpStatusEnum::values())],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name'        => 'الاسم الكامل',
            'governorate'      => 'المحافظة',
            'job_title'        => 'المسمى الوظيفي',
            'status'           => 'النتيجة',
            'score'            => 'العلامة',
            'interview_date'   => 'تاريخ المقابلة',
            'follow_up_status' => 'حالة المتابعة',
            'notes'            => 'الملاحظات',
        ];
    }
}
