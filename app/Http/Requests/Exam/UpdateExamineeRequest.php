<?php

namespace App\Http\Requests\Exam;

/**
 * All fields optional: fields not sent keep their current value (see ExamineeData).
 */
class UpdateExamineeRequest extends StoreExamineeRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => array_merge(['sometimes'], array_values(array_diff($rules, ['required']))))
            ->all();
    }
}
