<?php

namespace App\Http\Filters\Examinee;

use App\Enum\ExamResultEnum;
use App\Http\Filters\Filter;
use Illuminate\Validation\Rule;

class ExamineeFilter extends Filter
{
    /**
     * Get the validation rules that apply to the filter request.
     *
     * @return array
     */
    public static function rules(): array
    {
        return [
            'exam_session_id' => ['nullable', 'integer'],
            // none = لم تُدخل نتيجته بعد
            'result'          => ['nullable', Rule::in(array_merge(ExamResultEnum::values(), ['none']))],
        ];
    }

    /**
     * Apply filter query on related model.
     * @param  \Illuminate\Database\Eloquent\Builder &$query
     */
    public function apply(&$query)
    {
        if (!empty($this->filterData['exam_session_id']))
        {
            $query->where('exam_session_id', $this->filterData['exam_session_id']);
        }

        $result = $this->filterData['result'] ?? '';
        if ($result === 'none')
        {
            $query->whereNull('result');
        }
        elseif ($result !== '' && $result !== null)
        {
            $query->where('result', $result);
        }

        return $query;
    }
}
