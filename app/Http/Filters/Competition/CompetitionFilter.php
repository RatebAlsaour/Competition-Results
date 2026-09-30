<?php

namespace App\Http\Filters\Competition;

use App\Enum\CompetitionStatusEnum;
use App\Http\Filters\Filter;
use Illuminate\Validation\Rule;

class CompetitionFilter extends Filter
{
    /**
     * Get the validation rules that apply to the filter request.
     *
     * @return array
     */
    public static function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(CompetitionStatusEnum::values())],
        ];
    }

    /**
     * Apply filter query on related model.
     * @param  \Illuminate\Database\Eloquent\Builder &$query
     */
    public function apply(&$query)
    {
        if (!empty($this->filterData['status']))
        {
            $query->where('status', $this->filterData['status']);
        }
        return $query;
    }
}
