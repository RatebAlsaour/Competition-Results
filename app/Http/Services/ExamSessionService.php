<?php

namespace App\Http\Services;

use App\Exceptions\ErrorMsgException;
use App\Http\Repositories\ExamSessionRepo;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Http\Request;

/**
 * Schedule (date, time, place) and halls distribution of each language of an exam.
 */
class ExamSessionService
{
    public function __construct(
        protected ExamSessionRepo $sessionRepo,
        protected ExamService $examService,
    ) {}

    public function list(Exam $exam)
    {
        return $this->sessionRepo->forExam($exam);
    }

    public function update(Request $request, ExamSession $session): ExamSession
    {
        $this->sessionRepo->update($request, $session, ['session' => $session]);
        $this->examService->flush($session->exam);

        return $this->fresh($session);
    }

    /**
     * Copy date, time, place and notes of a session to every other session of the exam.
     */
    public function copyScheduleToAll(ExamSession $source): void
    {
        $source->exam->sessions()->whereKeyNot($source->id)->get()->each(
            fn (ExamSession $s) => $s->update([
                'exam_date'  => $source->exam_date,
                'start_time' => $source->start_time,
                'end_time'   => $source->end_time,
                'location'   => $source->location,
            ])
        );
        $this->examService->flush($source->exam);
    }

    /**
     * Distribute exam numbers 1..max over halls of equal capacity: «القاعة 1» (1–35), «القاعة 2» (36–70)...
     */
    public function generateHalls(ExamSession $session, int $capacity, string $prefix = 'القاعة', int $startAt = 1): ExamSession
    {
        $max = $this->sessionRepo->maxExamNumber($session);
        if ($max === 0)
        {
            throw new ErrorMsgException('لا يوجد متقدمون في هذه اللغة لتوزيعهم على القاعات', 422);
        }

        $halls = [];
        for ($from = 1, $i = 0; $from <= $max; $from += $capacity, $i++)
        {
            $halls[] = [
                'name'        => trim($prefix) . ' ' . ($startAt + $i),
                'from_number' => $from,
                'to_number'   => min($from + $capacity - 1, $max),
            ];
        }

        return $this->replaceHalls($session, $halls);
    }

    /**
     * @param array<int, array{name: string, building?: ?string, from_number: int, to_number: int}> $halls
     */
    public function replaceHalls(ExamSession $session, array $halls): ExamSession
    {
        usort($halls, fn ($a, $b) => $a['from_number'] <=> $b['from_number']);

        $previousTo = 0;
        foreach ($halls as $hall)
        {
            if ($hall['from_number'] > $hall['to_number'])
            {
                throw new ErrorMsgException("القاعة «{$hall['name']}»: بداية المجال أكبر من نهايته", 422);
            }
            if ($hall['from_number'] <= $previousTo)
            {
                throw new ErrorMsgException("القاعة «{$hall['name']}»: مجال الأرقام يتداخل مع قاعة أخرى", 422);
            }
            $previousTo = $hall['to_number'];
        }

        $this->sessionRepo->replaceHalls($session, array_map(fn ($h) => [
            'name'        => trim($h['name']),
            'building'    => trim($h['building'] ?? '') ?: null,
            'from_number' => (int) $h['from_number'],
            'to_number'   => (int) $h['to_number'],
        ], $halls));

        $this->examService->flush($session->exam);

        return $this->fresh($session);
    }

    private function fresh(ExamSession $session): ExamSession
    {
        return $session->fresh()->load('halls')->loadCount('examinees')->loadMax('examinees', 'exam_number');
    }
}
