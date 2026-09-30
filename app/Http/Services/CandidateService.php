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
        return [
            'governorates'     => $this->candidateRepo->governorates($competition),
            'job_titles'       => $this->candidateRepo->jobTitles($competition, request()->input('governorate')),
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
            return $candidate->refresh();
        });
    }

    public function update(Request $request, Candidate $candidate): Candidate
    {
        return DB::transaction(function () use ($request, $candidate) {
            $oldGroup = [$candidate->governorate, $candidate->job_title];

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
    }
}
