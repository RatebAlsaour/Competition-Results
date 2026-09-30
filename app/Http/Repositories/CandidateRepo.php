<?php

namespace App\Http\Repositories;

use App\Enum\CandidateStatusEnum;
use App\Enum\RankingMethodEnum;
use App\Http\Classes\ArabicNormalizer;
use App\Http\DTOs\CandidateData;
use App\Http\Filters\Candidate\CandidateFilter;
use App\Interfaces\IHasDataTransferObjects;
use App\Interfaces\IHasFilterable;
use App\Interfaces\IHasOrderable;
use App\Interfaces\IHasSearchable;
use App\Models\Candidate;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CandidateRepo extends BaseRepo implements IHasSearchable, IHasFilterable, IHasOrderable, IHasDataTransferObjects
{
    public $filtersKeys = [
        'candidate' => CandidateFilter::class,
    ];

    public $searchable = ['search_key'];

    public $orderable = ['governorate.asc', 'job_title.asc', 'rank.asc'];

    public function __construct()
    {
        parent::__construct(new Candidate(), CandidateData::class);
    }

    /**
     * Search on the normalized name so "احمد" matches "أحمد".
     */
    protected function applyNormalSearch(&$query, $value)
    {
        $query->where('search_key', 'LIKE', '%' . ArabicNormalizer::searchKey($value) . '%');
    }

    /**
     * Filtered + searched + ordered query scoped to a competition.
     */
    public function forCompetition(Competition $competition): Builder
    {
        return $this->fetch()->where('competition_id', $competition->id);
    }

    public function governorates(Competition $competition): array
    {
        $available = $competition->candidates()->distinct()->pluck('governorate')->all();
        $order = array_flip(config('results.governorates_order'));

        usort($available, fn ($a, $b) => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        return $available;
    }

    public function jobTitles(Competition $competition, ?string $governorate = null): array
    {
        return $competition->candidates()
            ->when($governorate, fn ($q) => $q->where('governorate', $governorate))
            ->groupBy('job_title')
            ->orderByRaw('MIN(COALESCE(source_row, 2147483647)), MIN(id)')
            ->pluck('job_title')
            ->all();
    }

    public function ranked(Competition $competition, string $governorate, string $jobTitle)
    {
        return $competition->candidates()
            ->where('governorate', $governorate)
            ->where('job_title', $jobTitle)
            ->orderBy('rank')
            ->get(['rank', 'full_name', 'status']);
    }

    /**
     * All public fields of a competition in one lightweight query, grouped by
     * "governorate|job title" and ordered by rank.
     *
     * @return array<string, array<int, object>>
     */
    public function publicRowsGrouped(Competition $competition): array
    {
        return $competition->candidates()->toBase()
            ->orderBy('governorate')->orderBy('job_title')->orderBy('rank')
            ->get(['governorate', 'job_title', 'rank', 'full_name', 'status'])
            ->groupBy(fn ($row) => $row->governorate . '|' . $row->job_title)
            ->map(fn ($rows) => $rows->all())
            ->all();
    }

    /**
     * Bulk insert rows already transformed by CandidateData.
     */
    public function insertMany(array $rows): void
    {
        $now = now();
        foreach (array_chunk($rows, 500) as $chunk)
        {
            $this->model->query()->insert(array_map(fn (array $row) => array_merge($row, [
                'follow_up_status' => $row['follow_up_status'] ?? 'pending',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]), $chunk));
        }
    }

    public function deleteForCompetition(Competition $competition): void
    {
        $competition->candidates()->delete();
    }

    /**
     * Recompute rank inside each (governorate + job title) group.
     * Limit to one group by passing governorate and job title.
     */
    public function rerank(Competition $competition, ?string $governorate = null, ?string $jobTitle = null): void
    {
        $order = $competition->ranking_method === RankingMethodEnum::SHEET
            ? 'COALESCE(source_row, 2147483647) ASC, id ASC'
            : 'COALESCE(score, -1) DESC, COALESCE(source_row, 2147483647) ASC, id ASC';

        $rows = $competition->candidates()
            ->when($governorate, fn ($q) => $q->where('governorate', $governorate))
            ->when($jobTitle, fn ($q) => $q->where('job_title', $jobTitle))
            ->orderBy('governorate')->orderBy('job_title')->orderByRaw($order)
            ->get(['id', 'governorate', 'job_title', 'rank']);

        DB::transaction(function () use ($rows) {
            $counters = [];
            foreach ($rows as $row)
            {
                $key = $row->governorate . '|' . $row->job_title;
                $rank = $counters[$key] = ($counters[$key] ?? 0) + 1;
                if ((int) $row->rank !== $rank)
                {
                    $this->model->query()->whereKey($row->id)->update(['rank' => $rank]);
                }
            }
        });
    }

    /**
     * Dashboard statistics for a competition.
     */
    public function stats(Competition $competition): array
    {
        $base = fn () => $competition->candidates()->toBase();

        $byStatus = $base()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $byFollowUp = $base()->selectRaw('follow_up_status, COUNT(*) as c')->groupBy('follow_up_status')->pluck('c', 'follow_up_status');
        $primaryFollowUp = $base()->where('status', CandidateStatusEnum::PRIMARY->value)
            ->selectRaw('follow_up_status, COUNT(*) as c')->groupBy('follow_up_status')->pluck('c', 'follow_up_status');

        $byGovernorate = $base()
            ->selectRaw("governorate,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'primary' THEN 1 ELSE 0 END) as primary_count,
                SUM(CASE WHEN status = 'reserve' THEN 1 ELSE 0 END) as reserve_count,
                SUM(CASE WHEN follow_up_status = 'contracted' THEN 1 ELSE 0 END) as contracted_count,
                COUNT(DISTINCT job_title) as job_titles")
            ->groupBy('governorate')
            ->get();

        $order = array_flip(config('results.governorates_order'));

        return [
            'total'             => (int) $byStatus->sum(),
            'primary'           => (int) ($byStatus['primary'] ?? 0),
            'reserve'           => (int) ($byStatus['reserve'] ?? 0),
            'job_titles'        => $base()->distinct()->count('job_title'),
            'follow_up'         => $byFollowUp->map(fn ($c) => (int) $c)->all(),
            'primary_follow_up' => $primaryFollowUp->map(fn ($c) => (int) $c)->all(),
            'by_governorate'    => $byGovernorate
                ->sortBy(fn ($g) => [$order[$g->governorate] ?? PHP_INT_MAX, $g->governorate])
                ->map(fn ($g) => [
                    'governorate' => $g->governorate,
                    'job_titles'  => (int) $g->job_titles,
                    'total'       => (int) $g->total,
                    'primary'     => (int) $g->primary_count,
                    'reserve'     => (int) $g->reserve_count,
                    'contracted'  => (int) $g->contracted_count,
                ])
                ->values()
                ->all(),
        ];
    }
}
