<?php

namespace App\Http\Filters\Candidate;

use App\Enum\CandidateStatusEnum;
use App\Enum\FollowUpStatusEnum;
use App\Http\Filters\Filter;
use Illuminate\Validation\Rule;

class CandidateFilter extends Filter
{
    /**
     * Get the validation rules that apply to the filter request.
     *
     * @return array
     */
    public static function rules(): array
    {
        return [
            'governorate'      => ['nullable', 'string', 'max:100'],
            'job_title'        => ['nullable', 'string', 'max:150'],
            'status'           => ['nullable', Rule::in(CandidateStatusEnum::values())],
            'follow_up_status' => ['nullable', Rule::in(FollowUpStatusEnum::values())],
        ];
    }

    /**
     * Apply filter query on related model.
     * @param  \Illuminate\Database\Eloquent\Builder &$query
     */
    public function apply(&$query)
    {
        foreach (array_keys(static::rules()) as $column)
        {
            if (($this->filterData[$column] ?? '') !== '')
            {
                $query->where($column, $this->filterData[$column]);
            }
        }
        return $query;
    }
}
