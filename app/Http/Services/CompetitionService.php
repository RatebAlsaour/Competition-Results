<?php

namespace App\Http\Services;

use App\Http\Repositories\CandidateRepo;
use App\Http\Repositories\CompetitionRepo;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompetitionService
{
    public const PUBLIC_LIST_SCOPE = 'competitions';

    public function __construct(
        protected CompetitionRepo $competitionRepo,
        protected CandidateRepo $candidateRepo,
        protected ResultsCacheService $cache,
        protected ResultsSnapshotService $snapshot,
        protected SecurityLogService $securityLog,
    ) {}

    public function list()
    {
        return $this->competitionRepo
            ->withCounts($this->competitionRepo->fetch())
            ->paginate(request()->input(config('pagination.request_max_key'), 20));
    }

    public function show(Competition $competition): Competition
    {
        return $competition->loadCount('candidates');
    }

    public function create(Request $request): Competition
    {
        $request->merge(['slug' => $this->uniqueSlug($request->input('slug') ?: $request->input('title'))]);

        $competition = $this->competitionRepo->store($request);
        $this->flush($competition);

        // تحميل القيم الافتراضية من قاعدة البيانات (الحالة، طريقة الترتيب)
        return $competition->refresh();
    }

    public function update(Request $request, Competition $competition): Competition
    {
        if ($request->filled('slug'))
        {
            $request->merge(['slug' => $this->uniqueSlug($request->input('slug'), $competition->id)]);
        }

        $rankingChanged = $request->filled('ranking_method')
            && $request->input('ranking_method') !== $competition->ranking_method->value;

        $oldStatus = $competition->status->value;

        $this->competitionRepo->update($request, $competition, ['competition' => $competition]);

        if ($rankingChanged)
        {
            $this->candidateRepo->rerank($competition);
        }

        $this->flush($competition);

        $competition->refresh();
        $this->securityLog->info('competition.updated', [
            'competition_id' => $competition->id,
            'title'          => $competition->title,
            'fields'         => array_keys($request->all()),
            'status'         => $oldStatus !== $competition->status->value ? "{$oldStatus} → {$competition->status->value}" : null,
        ]);

        return $competition;
    }

    public function delete(Competition $competition): void
    {
        $count = $competition->candidates()->count();
        $competition->delete();
        $this->flush($competition);

        $this->securityLog->warning('competition.deleted', [
            'competition_id' => $competition->id,
            'title'          => $competition->title,
            'candidates'     => $count,
        ]);
    }

    /**
     * Dashboard statistics — cached until any candidate of the competition changes.
     */
    public function stats(Competition $competition): array
    {
        return $this->cache->remember(self::adminScope($competition), 'stats',
            fn () => $this->candidateRepo->stats($competition));
    }

    /**
     * Refresh everything the public sees (API cache + static JSON files).
     * Runs after the surrounding DB transaction commits, so a rolled-back change is never published.
     */
    public function flush(Competition $competition): void
    {
        DB::afterCommit(function () use ($competition) {
            $this->cache->flush($competition->id);
            $this->cache->flush(self::PUBLIC_LIST_SCOPE);
            $this->cache->flush(self::adminScope($competition));
            $this->snapshot->refresh($competition);
        });
    }

    /**
     * Invalidate dashboard-only caches (stats, filter lists) — ex: a follow-up status changed.
     */
    public function flushAdmin(Competition $competition): void
    {
        DB::afterCommit(fn () => $this->cache->flush(self::adminScope($competition)));
    }

    public static function adminScope(Competition $competition): string
    {
        return 'admin:' . $competition->id;
    }

    /**
     * Latin slug for public URLs; Arabic titles fall back to a random suffix.
     */
    private function uniqueSlug(?string $value, ?int $exceptId = null): string
    {
        $base = Str::slug((string) $value) ?: 'competition-' . Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;
        while ($this->competitionRepo->slugExists($slug, $exceptId))
        {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
