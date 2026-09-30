<?php

namespace App\Http\Repositories;

use App\Enum\CandidateStatusEnum;
use App\Http\DTOs\CompetitionData;
use App\Http\Filters\Competition\CompetitionFilter;
use App\Interfaces\IHasDataTransferObjects;
use App\Interfaces\IHasFilterable;
use App\Interfaces\IHasOrderable;
use App\Interfaces\IHasSearchable;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;

class CompetitionRepo extends BaseRepo implements IHasSearchable, IHasFilterable, IHasOrderable, IHasDataTransferObjects
{
    public $filtersKeys = [
        'competition' => CompetitionFilter::class,
    ];

    public $searchable = ['title', 'slug'];

    public $orderable = ['created_at.desc'];

    public function __construct()
    {
        parent::__construct(new Competition(), CompetitionData::class);
    }

    /**
     * Dashboard listing with candidates counts.
     */
    public function withCounts(Builder $query): Builder
    {
        return $query->withCount([
            'candidates',
            'candidates as primary_count' => fn ($q) => $q->where('status', CandidateStatusEnum::PRIMARY),
            'candidates as reserve_count' => fn ($q) => $q->where('status', CandidateStatusEnum::RESERVE),
        ]);
    }

    public function findPublishedBySlug(string $slug): Competition
    {
        return $this->model->query()->published()->where('slug', $slug)->firstOrFail();
    }

    public function publishedList()
    {
        return $this->model->query()->published()->orderByDesc('published_at')->get();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->model->query()
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
