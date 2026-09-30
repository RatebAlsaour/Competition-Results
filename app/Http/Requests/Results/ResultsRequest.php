<?php

namespace App\Http\Requests\Results;

class ResultsRequest extends JobTitlesRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'job_title' => ['required', 'string', 'max:150'],
        ]);
    }
}
