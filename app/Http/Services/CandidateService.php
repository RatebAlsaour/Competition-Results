<?php

namespace App\Http\Services;

use App\Enum\CandidateStatusEnum;
use App\Enum\FollowUpStatusEnum;
use App\Http\Repositories\CandidateRepo;
use App\Models\Candidate;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateService
{
    /** Fields that affect what the public portal shows (name, group, result, order). */
    private const PUBLIC_FIELDS = ['full_name', 'governorate', 'job_title', 'status', 'score'];

    public function __construct(
        protected CandidateRepo $candidateRepo,
        protected CompetitionService $competitionService,
        protected SecurityLogService $securityLog,
        protected ResultsCacheService $cache,
    ) {}

    public function paginate(Competition $competition)
    {
        return $this->candidateRepo
            ->forCompetition($competition)
            ->paginate(min((int) request()->input(config('pagination.request_max_key'), 25), 200));
    }

    /**
     * Values for the dashboard filter dropdowns.
     */
    public function filterOptions(Competition $competition): array
    {
        $governorate = request()->input('governorate');
        $scope = CompetitionService::adminScope($competition);

        return [
            'governorates'     => $this->cache->remember($scope, 'governorates', fn () => $this->candidateRepo->governorates($competition)),
            'job_titles'       => $this->cache->remember($scope, 'titles:' . $governorate, fn () => $this->candidateRepo->jobTitles($competition, $governorate)),
            'statuses'         => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], CandidateStatusEnum::cases()),
            'follow_up_statuses' => FollowUpStatusEnum::options(),
        ];
    }

    public function create(Request $request, Competition $competition): Candidate
    {
        return DB::transaction(function () use ($request, $competition) {
            $candidate = $this->candidateRepo->store($request, ['competition' => $competition]);
            $this->candidateRepo->rerank($competition, $candidate->governorate, $candidate->job_title);
            $this->competitionService->flush($competition);
            $this->securityLog->info('candidate.created', ['candidate_id' => $candidate->id, 'competition_id' => $competition->id, 'full_name' => $candidate->full_name]);
            return $candidate->refresh();
        });
    }

    public function update(Request $request, Candidate $candidate): Candidate
    {
        return DB::transaction(function () use ($request, $candidate) {
            $oldGroup = [$candidate->governorate, $candidate->job_title];
            $before = $candidate->only(self::PUBLIC_FIELDS);

            $this->candidateRepo->update($request, $candidate, ['candidate' => $candidate]);

            $competition = $candidate->competition;
            if ($candidate->wasChanged(['governorate', 'job_title', 'score']))
            {
                $this->candidateRepo->rerank($competition, ...$oldGroup);
                $this->candidateRepo->rerank($competition, $candidate->governorate, $candidate->job_title);
            }
            // المتابعة والملاحظات داخلية: لا حاجة لإعادة توليد الملفات العامة عند تغييرها
            if ($candidate->wasChanged(self::PUBLIC_FIELDS))
            {
                $this->competitionService->flush($competition);

                // تغيير بيانات منشورة (اسم، نتيجة، علامة...) يُسجَّل مع القيم القديمة والجديدة
                $changed = array_intersect_key($candidate->getChanges(), array_flip(self::PUBLIC_FIELDS));
                $this->securityLog->info('candidate.updated', [
                    'candidate_id' => $candidate->id,
                    'competition_id' => $competition->id,
                    'old' => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, array_intersect_key($before, $changed)),
                    'new' => $changed,
                ]);
            }
            elseif ($candidate->wasChanged())
            {
                // متابعة أو ملاحظات فقط: تتغير إحصائيات لوحة التحكم فقط
                $this->competitionService->flushAdmin($competition);
            }

            return $candidate->refresh();
        });
    }

    public function delete(Candidate $candidate): void
    {
        DB::transaction(function () use ($candidate) {
            $competition = $candidate->competition;
            $candidate->delete();
            $this->candidateRepo->rerank($competition, $candidate->governorate, $candidate->job_title);
            $this->competitionService->flush($competition);
        });

        $this->securityLog->warning('candidate.deleted', [
            'candidate_id'   => $candidate->id,
            'competition_id' => $candidate->competition_id,
            'full_name'      => $candidate->full_name,
            'governorate'    => $candidate->governorate,
            'job_title'      => $candidate->job_title,
            'status'         => $candidate->status->value,
        ]);
    }
}
