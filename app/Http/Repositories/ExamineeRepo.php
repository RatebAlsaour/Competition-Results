<?php

namespace App\Http\Repositories;

use App\Http\Classes\ArabicNormalizer;
use App\Http\DTOs\ExamineeData;
use App\Http\Filters\Examinee\ExamineeFilter;
use App\Interfaces\IHasDataTransferObjects;
use App\Interfaces\IHasFilterable;
use App\Interfaces\IHasOrderable;
use App\Interfaces\IHasSearchable;
use App\Models\Exam;
use App\Models\Examinee;
use Illuminate\Database\Eloquent\Builder;

class ExamineeRepo extends BaseRepo implements IHasSearchable, IHasFilterable, IHasOrderable, IHasDataTransferObjects
{
    public $filtersKeys = [
        'examinee' => ExamineeFilter::class,
    ];

    public $searchable = ['search_key'];

    public $orderable = ['exam_session_id.asc', 'exam_number.asc'];

    public function __construct()
    {
        parent::__construct(new Examinee(), ExamineeData::class);
    }

    /**
     * Word by word search on the normalized name (same rules as candidates),
     * or by file number / exam number when the text is a number.
     */
    protected function applyNormalSearch(&$query, $value)
    {
        $value = str_replace(['%', '_'], '', ArabicNormalizer::searchKey($value));

        if ($value !== '' && ctype_digit($value))
        {
            $query->where(fn ($q) => $q->where('exam_number', (int) $value)->orWhere('file_number', 'LIKE', $value . '%'));
            return;
        }

        foreach (array_filter(explode(' ', $value)) as $word)
        {
            if (mb_strlen($word) <= 2)
            {
                $query->where(fn ($q) => $q->where('search_key', 'LIKE', $word . '%')
                    ->orWhere('search_key', 'LIKE', '% ' . $word . '%'));
            }
            else
            {
                $query->whereRaw("REPLACE(search_key, ' ', '') LIKE ?", ['%' . $word . '%']);
            }
        }
    }

    public function forExam(Exam $exam): Builder
    {
        return $this->fetch()->where('exam_id', $exam->id)->with('session.halls');
    }

    /**
     * Bulk insert rows already transformed by ExamineeData.
     */
    public function insertMany(array $rows): void
    {
        $now = now();
        foreach (array_chunk($rows, 500) as $chunk)
        {
            $this->model->query()->insert(array_map(fn (array $row) => array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]), $chunk));
        }
    }

    public function deleteForExam(Exam $exam): void
    {
        $exam->examinees()->delete();
    }

    /**
     * Dashboard statistics per session.
     */
    public function stats(Exam $exam): array
    {
        $rows = $exam->examinees()->toBase()
            ->selectRaw("exam_session_id,
                COUNT(*) as total,
                SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN result = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN result IS NULL THEN 1 ELSE 0 END) as pending")
            ->groupBy('exam_session_id')
            ->get()
            ->keyBy('exam_session_id');

        $sum = fn (string $key) => (int) $rows->sum($key);

        return [
            'total'      => $sum('total'),
            'passed'     => $sum('passed'),
            'failed'     => $sum('failed'),
            'absent'     => $sum('absent'),
            'pending'    => $sum('pending'),
            'by_session' => $rows->map(fn ($r) => [
                'total'   => (int) $r->total,
                'passed'  => (int) $r->passed,
                'failed'  => (int) $r->failed,
                'absent'  => (int) $r->absent,
                'pending' => (int) $r->pending,
            ])->all(),
        ];
    }

    /**
     * All examinees with their session and halls, for the public snapshot.
     */
    public function publicRows(Exam $exam)
    {
        return $exam->examinees()
            ->orderBy('exam_session_id')->orderBy('exam_number')
            ->get(['exam_session_id', 'exam_number', 'file_number', 'full_name', 'hall_name', 'result', 'score', 'result_note']);
    }
}
