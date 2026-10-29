<?php

namespace App\Http\Services;

use App\Enum\ExamResultEnum;
use App\Exceptions\ErrorMsgException;
use App\Http\Repositories\ExamineeRepo;
use App\Models\Exam;
use App\Models\Examinee;
use Illuminate\Http\Request;

class ExamineeService
{
    /** Fields that affect what the public sees (private notes don't). */
    private const PUBLIC_FIELDS = ['full_name', 'exam_session_id', 'exam_number', 'file_number', 'hall_name', 'result', 'score', 'result_note'];

    public function __construct(
        protected ExamineeRepo $examineeRepo,
        protected ExamService $examService,
        protected SecurityLogService $securityLog,
    ) {}

    public function paginate(Exam $exam)
    {
        return $this->examineeRepo
            ->forExam($exam)
            ->paginate(min((int) request()->input(config('pagination.request_max_key'), 25), 200));
    }

    public function options(Exam $exam): array
    {
        return [
            'sessions' => $exam->sessions()->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'results'  => ExamResultEnum::options(),
        ];
    }

    public function create(Request $request, Exam $exam): Examinee
    {
        $this->assertSessionBelongs($exam, (int) $request->input('exam_session_id'));

        $examinee = $this->examineeRepo->store($request, ['exam' => $exam]);
        $this->examService->flush($exam);
        $this->securityLog->info('examinee.created', ['examinee_id' => $examinee->id, 'exam_id' => $exam->id, 'full_name' => $examinee->full_name]);

        return $examinee->refresh()->load('session.halls');
    }

    public function update(Request $request, Examinee $examinee): Examinee
    {
        if ($request->filled('exam_session_id'))
        {
            $this->assertSessionBelongs($examinee->exam, (int) $request->input('exam_session_id'));
        }

        $before = $examinee->only(self::PUBLIC_FIELDS);

        $this->examineeRepo->update($request, $examinee, ['examinee' => $examinee]);

        if ($examinee->wasChanged(self::PUBLIC_FIELDS))
        {
            $this->examService->flush($examinee->exam);

            $changed = array_intersect_key($examinee->getChanges(), array_flip(self::PUBLIC_FIELDS));
            $this->securityLog->info('examinee.updated', [
                'examinee_id' => $examinee->id,
                'exam_id'     => $examinee->exam_id,
                'old'         => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, array_intersect_key($before, $changed)),
                'new'         => $changed,
            ]);
        }
        elseif ($examinee->wasChanged())
        {
            $this->examService->flushAdmin($examinee->exam);
        }

        return $examinee->refresh()->load('session.halls');
    }

    public function delete(Examinee $examinee): void
    {
        $exam = $examinee->exam;
        $examinee->delete();
        $this->examService->flush($exam);

        $this->securityLog->warning('examinee.deleted', [
            'examinee_id' => $examinee->id,
            'exam_id'     => $examinee->exam_id,
            'full_name'   => $examinee->full_name,
            'exam_number' => $examinee->exam_number,
        ]);
    }

    private function assertSessionBelongs(Exam $exam, int $sessionId): void
    {
        if (!$exam->sessions()->whereKey($sessionId)->exists())
        {
            throw new ErrorMsgException('اللغة المختارة لا تتبع هذا الامتحان', 422);
        }
    }
}
