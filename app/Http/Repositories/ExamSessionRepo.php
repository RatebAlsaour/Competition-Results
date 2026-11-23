<?php

namespace App\Http\Repositories;

use App\Http\DTOs\ExamSessionData;
use App\Interfaces\IHasDataTransferObjects;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Support\Facades\DB;

class ExamSessionRepo extends BaseRepo implements IHasDataTransferObjects
{
    public function __construct()
    {
        parent::__construct(new ExamSession(), ExamSessionData::class);
    }

    /**
     * Sessions with halls and examinees counts (largest first is the natural reading order).
     */
    public function forExam(Exam $exam)
    {
        return $exam->sessions()
            ->with('halls')
            ->withCount('examinees')
            ->withMax('examinees', 'exam_number')
            ->get();
    }

    /**
     * Find a session by name or create it (used by the import: one session per language).
     * «الإنكليزية» finds «إنكليزية» (see ExamSession::nameKey).
     */
    public function firstOrCreateByName(Exam $exam, string $name): ExamSession
    {
        return $this->findByName($exam, $name) ?? $exam->sessions()->create([
            'name'       => $name,
            'sort_order' => (int) $exam->sessions()->max('sort_order') + 1,
        ]);
    }

    public function findByName(Exam $exam, string $name): ?ExamSession
    {
        $key = ExamSession::nameKey($name);
        return $exam->sessions()->get()->first(fn (ExamSession $s) => ExamSession::nameKey($s->name) === $key);
    }

    /**
     * Session id of each name of the file (null when the language doesn't exist yet).
     *
     * @param string[] $names
     * @return array<string, ?int>
     */
    public function idsByName(Exam $exam, array $names): array
    {
        $ids = $exam->sessions()->get()->mapWithKeys(fn (ExamSession $s) => [ExamSession::nameKey($s->name) => $s->id]);
        return collect($names)->unique()->mapWithKeys(fn ($name) => [$name => $ids[ExamSession::nameKey($name)] ?? null])->all();
    }

    /**
     * Replace all halls of a session.
     *
     * @param array<int, array{name: string, from_number: int, to_number: int}> $halls
     */
    public function replaceHalls(ExamSession $session, array $halls): void
    {
        DB::transaction(function () use ($session, $halls) {
            $session->halls()->delete();
            foreach ($halls as $hall)
            {
                $session->halls()->create($hall);
            }
        });
        $session->unsetRelation('halls');
    }

    public function maxExamNumber(ExamSession $session): int
    {
        return (int) $session->examinees()->max('exam_number');
    }
}
