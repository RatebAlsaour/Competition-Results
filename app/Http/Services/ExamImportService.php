<?php

namespace App\Http\Services;

use App\Http\Classes\ArabicNormalizer;
use App\Http\DTOs\ExamineeData;
use App\Http\DTOs\ExamSheetReadResultData;
use App\Http\Repositories\ExamineeRepo;
use App\Http\Repositories\ExamSessionRepo;
use App\Interfaces\IExamSheetReader;
use App\Models\Exam;
use App\Models\Examinee;
use Illuminate\Support\Facades\DB;

/**
 * Two imports for an exam:
 *  - examinees: names + exam numbers (before the exam). Creates one session per language.
 *  - results:   result/score per exam number (after the exam). Only updates existing examinees.
 */
class ExamImportService
{
    public const TYPE_EXAMINEES = 'examinees';
    public const TYPE_RESULTS   = 'results';

    public const MODE_REPLACE = 'replace';   // حذف الموجودين واعتماد الملف
    public const MODE_APPEND  = 'append';    // إضافة كل أسطر الملف كما هي
    public const MODE_UPSERT  = 'upsert';    // الموجود يُحدَّث، وغير الموجود يُضاف

    public function __construct(
        protected IExamSheetReader $reader,
        protected ExamineeRepo $examineeRepo,
        protected ExamSessionRepo $sessionRepo,
        protected ExamService $examService,
        protected ExamSessionService $sessionService,
        protected SecurityLogService $securityLog,
    ) {}

    public function preview(string $path): ExamSheetReadResultData
    {
        return $this->reader->read($path);
    }

    /**
     * How many rows of the sheet match an existing examinee (would be updated) or not (would be added).
     *
     * @return array{existing: int, new: int}
     */
    public function compare(Exam $exam, ExamSheetReadResultData $sheet): array
    {
        $sessionIds = $exam->sessions()->pluck('id', 'name');

        $rows = array_map(fn (array $row) => [
            'exam_session_id' => $sessionIds[$row['session']] ?? null,
            'exam_number'     => is_numeric($row['exam_number']) ? (int) $row['exam_number'] : null,
            'search_key'      => ArabicNormalizer::searchKey($row['full_name']),
        ], $sheet->rows);

        $existing = count($this->matchExisting($exam, $rows));

        return ['existing' => $existing, 'new' => count($rows) - $existing];
    }

    /**
     * @param ?string $hallsSession the language that gets the «توزيع القاعات» table of the file (when it has no language column)
     * @return array{imported: int, updated: int, unchanged: int, skipped: int, sessions: int, halls: int}
     */
    public function importExaminees(Exam $exam, string $path, string $fileName, string $mode = self::MODE_REPLACE, ?string $hallsSession = null): array
    {
        $sheet = $this->reader->read($path);

        $this->securityLog->info('exam.import.examinees', [
            'exam_id'   => $exam->id,
            'file_name' => $fileName,
            'mode'      => $mode,
            'rows'      => count($sheet->rows),
            'replaced'  => $mode === self::MODE_REPLACE ? $exam->examinees()->count() : 0,
        ]);

        return DB::transaction(function () use ($exam, $sheet, $mode, $hallsSession) {
            if ($mode === self::MODE_REPLACE)
            {
                // الجلسات (المواعيد والقاعات) تبقى؛ تُحذف الأسماء فقط
                $this->examineeRepo->deleteForExam($exam);
            }

            $sessions = [];
            $rows = [];
            foreach ($sheet->rows as $row)
            {
                $session = $sessions[$row['session']] ??= $this->sessionRepo->firstOrCreateByName($exam, $row['session']);
                $rows[] = ExamineeData::fromObject((object) $row, ['exam' => $exam, 'session' => $session])->all();
            }

            $updated = $unchanged = 0;
            if ($mode === self::MODE_UPSERT)
            {
                [$rows, $updated, $unchanged] = $this->updateExisting($exam, $rows);
            }

            $this->examineeRepo->insertMany($rows);
            $halls = $this->applyHalls($exam, $sheet, $hallsSession);
            $this->examService->flush($exam);

            return [
                'imported'  => count($rows),
                'updated'   => $updated,
                'unchanged' => $unchanged,
                'skipped'   => count($sheet->errors),
                'sessions'  => count($sessions),
                'halls'     => $halls,
            ];
        });
    }

    /**
     * Replace the halls of every language that has rows in the file's «توزيع القاعات» table.
     * Overlapping ranges are refused (the whole import is rolled back).
     *
     * @return int number of halls saved
     */
    private function applyHalls(Exam $exam, ExamSheetReadResultData $sheet, ?string $hallsSession): int
    {
        $sessions = array_unique(array_filter(array_merge(array_column($sheet->halls, 'session'), [$hallsSession])));
        $count = 0;

        foreach ($sessions as $name)
        {
            $halls = $sheet->hallsFor($name, $hallsSession);
            if (!$halls) continue;

            $this->sessionService->replaceHalls($this->sessionRepo->firstOrCreateByName($exam, $name), $halls);
            $count += count($halls);
        }

        return $count;
    }

    /**
     * Update examinees that already exist and return the rows that are really new.
     * The sheet owns the name and the numbers; results, special halls and notes entered
     * from the dashboard are kept unless the sheet gives a value for them.
     *
     * @return array{0: array, 1: int, 2: int} [new rows, updated, unchanged]
     */
    private function updateExisting(Exam $exam, array $rows): array
    {
        $matches = $this->matchExisting($exam, $rows);
        $updated = 0;

        foreach ($matches as $index => $examinee)
        {
            $row = $rows[$index];

            $examinee->fill([
                'exam_number' => $row['exam_number'],
                'full_name'   => $row['full_name'],
                'search_key'  => $row['search_key'],
            ]);
            // الخانة الفارغة في الملف لا تمسح قيمة موجودة
            $examinee->fill(array_filter(
                array_intersect_key($row, array_flip(['file_number', 'admin_note', 'result', 'score'])),
                fn ($value) => $value !== null
            ));

            if ($examinee->isDirty())
            {
                $examinee->save();
                $updated++;
            }
        }

        return [array_values(array_diff_key($rows, $matches)), $updated, count($matches) - $updated];
    }

    /**
     * Find the existing examinee of each row, inside the same language. Each examinee is matched once.
     *  1. same exam number and same name
     *  2. same name (the exam number changed)
     *  3. same exam number (the name was corrected)
     *
     * @param array $rows each with exam_session_id, exam_number, search_key
     * @return array<int, Examinee> row index => examinee
     */
    private function matchExisting(Exam $exam, array $rows): array
    {
        $byBoth = $byName = $byNumber = [];
        foreach ($exam->examinees()->orderBy('id')->get() as $examinee)
        {
            $session = $examinee->exam_session_id;
            $byBoth["{$session}|{$examinee->exam_number}|{$examinee->search_key}"][] = $examinee;
            $byName["{$session}|{$examinee->search_key}"][] = $examinee;
            $byNumber["{$session}|{$examinee->exam_number}"][] = $examinee;
        }

        $matches = [];
        $used = [];
        $take = function (array $pool, string $key) use (&$used): ?Examinee {
            foreach ($pool[$key] ?? [] as $examinee)
            {
                if (!isset($used[$examinee->id]))
                {
                    $used[$examinee->id] = true;
                    return $examinee;
                }
            }
            return null;
        };

        $passes = [
            [$byBoth,   fn (array $r) => "{$r['exam_session_id']}|{$r['exam_number']}|{$r['search_key']}"],
            [$byName,   fn (array $r) => "{$r['exam_session_id']}|{$r['search_key']}"],
            [$byNumber, fn (array $r) => "{$r['exam_session_id']}|{$r['exam_number']}"],
        ];

        foreach ($passes as [$pool, $key])
        {
            foreach ($rows as $index => $row)
            {
                if (isset($matches[$index]) || !$row['exam_session_id']) continue;

                if ($examinee = $take($pool, $key($row)))
                {
                    $matches[$index] = $examinee;
                }
            }
        }

        return $matches;
    }

    /**
     * Match by language + exam number and set result/score.
     *
     * @return array{updated: int, unmatched: array<int, array{row: string, message: string}>}
     */
    public function importResults(Exam $exam, string $path, string $fileName): array
    {
        $sheet = $this->reader->read($path);

        $this->securityLog->info('exam.import.results', ['exam_id' => $exam->id, 'file_name' => $fileName, 'rows' => count($sheet->rows)]);

        return DB::transaction(function () use ($exam, $sheet) {
            $sessionIds = $exam->sessions()->pluck('id', 'name');
            $updated = 0;
            $unmatched = [];

            foreach ($sheet->rows as $row)
            {
                if ($row['result'] === null && $row['score'] === null) continue;

                $sessionId = $sessionIds[$row['session']] ?? null;
                $examinees = $sessionId
                    ? $exam->examinees()->where('exam_session_id', $sessionId)->where('exam_number', $row['exam_number'])->get()
                    : collect();

                // عند تكرار الرقم الامتحاني نطابق بالاسم أيضاً
                if ($examinees->count() > 1)
                {
                    $key = ArabicNormalizer::searchKey($row['full_name']);
                    $examinees = $examinees->where('search_key', $key);
                }

                if ($examinees->count() !== 1)
                {
                    $unmatched[] = [
                        'row'     => $row['source'],
                        'message' => "{$row['full_name']} ({$row['session']} — {$row['exam_number']}): " . ($sessionId ? 'لا يوجد متقدم بهذا الرقم' : 'لغة غير موجودة في الامتحان'),
                    ];
                    continue;
                }

                $examinees->first()->update(['result' => $row['result'], 'score' => $row['score']]);
                $updated++;
            }

            $this->examService->flush($exam);

            return ['updated' => $updated, 'unmatched' => $unmatched];
        });
    }
}
