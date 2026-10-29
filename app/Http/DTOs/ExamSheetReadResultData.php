<?php

namespace App\Http\DTOs;

class ExamSheetReadResultData extends BaseDTO
{
    /** @var array<int, array<string, mixed>> الأسطر الصالحة */
    public array $rows = [];

    /** @var array<int, array{row: string, message: string}> أسطر لم تُقرأ */
    public array $errors = [];

    /** @var array<int, array{row: string, message: string}> أسطر قُرئت لكن تحتاج مراجعة */
    public array $warnings = [];

    /** أرقام امتحانية بلا اسم (احتياطية) تم تجاهلها */
    public int $placeholders = 0;

    /** هل يحتوي الملف عمود نتيجة؟ */
    public bool $has_results = false;

    /** @var array<int, array{name: string, building: ?string, from_number: int, to_number: int, session: ?string, source: string}> جدول «توزيع القاعات» إن وُجد */
    public array $halls = [];

    public static function fromObject($object, ...$args): self
    {
        return new self([
            'rows'         => $object->rows ?? [],
            'errors'       => $object->errors ?? [],
            'warnings'     => $object->warnings ?? [],
            'placeholders' => $object->placeholders ?? 0,
            'has_results'  => $object->has_results ?? false,
            'halls'        => $object->halls ?? [],
        ]);
    }

    /**
     * Halls of a session: the ones naming it in their «اللغة» column, plus the ones without a language
     * when $session is the language chosen for them.
     */
    public function hallsFor(string $session, ?string $hallsSession): array
    {
        return array_values(array_filter(
            $this->halls,
            fn (array $hall) => $hall['session'] !== null ? $hall['session'] === $session : $session === $hallsSession
        ));
    }

    /**
     * Exam numbers of a session that no hall covers, as ranges: [[327, 336], [1500, 1500]].
     */
    public function uncovered(string $session, array $halls): array
    {
        $numbers = array_unique(array_column(array_filter($this->rows, fn ($row) => $row['session'] === $session), 'exam_number'));
        sort($numbers);

        $ranges = [];
        foreach ($numbers as $number)
        {
            if ($this->covered($number, $halls)) continue;

            $last = count($ranges) - 1;
            $last >= 0 && $ranges[$last][1] === $number - 1
                ? $ranges[$last][1] = $number
                : $ranges[] = [$number, $number];
        }

        return $ranges;
    }

    private function covered(int $number, array $halls): bool
    {
        foreach ($halls as $hall)
        {
            if ($number >= $hall['from_number'] && $number <= $hall['to_number']) return true;
        }
        return false;
    }

    /**
     * Languages that have a «توزيع القاعات» table, with the exam numbers left without a hall.
     * When the halls table has no language column, the coverage is computed for every language
     * so the admin can see which one it fits.
     *
     * @return array<string, array{halls: int, uncovered: array, uncovered_count: int}>
     */
    public function hallsCoverage(): array
    {
        $coverage = [];
        foreach ($this->summary() as $item)
        {
            $session = $item['session'];
            $halls = $this->hallsFor($session, $session);
            if (!$halls) continue;

            $uncovered = $this->uncovered($session, $halls);
            $coverage[$session] = [
                'halls'           => count($halls),
                'uncovered'       => array_slice($uncovered, 0, 50),
                'uncovered_count' => count(array_filter(
                    $this->rows,
                    fn ($row) => $row['session'] === $session && !$this->covered($row['exam_number'], $halls)
                )),
            ];
        }
        return $coverage;
    }

    /**
     * Count per session: [{session, total, passed, failed, absent}]
     */
    public function summary(): array
    {
        return collect($this->rows)
            ->groupBy('session')
            ->map(fn ($rows, $session) => [
                'session' => $session,
                'total'   => $rows->count(),
                'passed'  => $rows->where('result', 'passed')->count(),
                'failed'  => $rows->where('result', 'failed')->count(),
                'absent'  => $rows->where('result', 'absent')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
