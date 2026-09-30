<?php

namespace App\Http\Services;

use App\Enum\CandidateStatusEnum;
use App\Http\Repositories\CandidateRepo;
use App\Http\Repositories\CompetitionRepo;
use App\Models\Competition;

/**
 * Public (read-only, cached) results for published competitions.
 */
class ResultsService
{
    public function __construct(
        protected CompetitionRepo $competitionRepo,
        protected CandidateRepo $candidateRepo,
        protected ResultsCacheService $cache,
    ) {}

    public function competitions()
    {
        return $this->cache->remember(CompetitionService::PUBLIC_LIST_SCOPE, 'list',
            fn () => $this->competitionRepo->publishedList());
    }

    public function competition(string $slug): Competition
    {
        return $this->competitionRepo->findPublishedBySlug($slug);
    }

    public function governorates(Competition $competition): array
    {
        return $this->cache->remember($competition->id, 'governorates',
            fn () => $this->candidateRepo->governorates($competition));
    }

    public function jobTitles(Competition $competition, string $governorate): array
    {
        return $this->cache->remember($competition->id, 'titles:' . $governorate,
            fn () => $this->candidateRepo->jobTitles($competition, $governorate));
    }

    public function results(Competition $competition, string $governorate, string $jobTitle): array
    {
        return $this->cache->remember($competition->id, 'results:' . $governorate . '|' . $jobTitle, function () use ($competition, $governorate, $jobTitle) {
            $candidates = $this->candidateRepo->ranked($competition, $governorate, $jobTitle)
                ->map(fn ($c) => [
                    'seq'    => $c->rank,
                    'name'   => $c->full_name,
                    'status' => $c->status->value,
                ])
                ->all();

            $primary = count(array_filter($candidates, fn ($c) => $c['status'] === CandidateStatusEnum::PRIMARY->value));

            return [
                'governorate'  => $governorate,
                'jobTitle'     => $jobTitle,
                'total'        => count($candidates),
                'primaryCount' => $primary,
                'reserveCount' => count($candidates) - $primary,
                'candidates'   => $candidates,
            ];
        });
    }
}
