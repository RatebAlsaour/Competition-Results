<?php

namespace App\Http\Requests\Exam;

class UpdateExamRequest extends StoreExamRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
    }
}
