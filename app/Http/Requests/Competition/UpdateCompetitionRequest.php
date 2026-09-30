<?php

namespace App\Http\Requests\Competition;

class UpdateCompetitionRequest extends StoreCompetitionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
    }
}
