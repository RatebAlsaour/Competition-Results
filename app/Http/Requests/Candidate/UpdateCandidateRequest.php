<?php

namespace App\Http\Requests\Candidate;

/**
 * All fields optional: fields not sent keep their current value (see CandidateData).
 */
class UpdateCandidateRequest extends StoreCandidateRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => array_merge(['sometimes'], array_values(array_diff($rules, ['required']))))
            ->all();
    }
}
