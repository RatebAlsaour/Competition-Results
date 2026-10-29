<?php

namespace App\Http\Services;

use App\Http\DTOs\CandidateData;
use App\Http\DTOs\SheetReadResultData;
use App\Http\Repositories\CandidateRepo;
use App\Http\Repositories\ResultImportRepo;
use App\Interfaces\IResultsSheetReader;
use App\Models\Candidate;
use App\Models\Competition;
use App\Models\ResultImport;
use Illuminate\Support\Facades\DB;

class ResultsImportService
{
    public const MODE_REPLACE = 'replace';   // حذف الموجودين واعتماد الملف
    public const MODE_APPEND  = 'append';    // إضافة كل أسطر الملف كما هي
    public const MODE_UPSERT  = 'upsert';    // الموجود يُحدَّث، وغير الموجود يُضاف

    /** Fields the sheet owns. Follow-up status and notes belong to the dashboard and are never touched. */
    private const SHEET_FIELDS = ['full_name', 'search_key', 'interview_date', 'score', 'status', 'source_row'];

    public function __construct(
        protected IResultsSheetReader $reader,
        protected CandidateRepo $candidateRepo,
        protected ResultImportRepo $importRepo,
        protected CompetitionService $competitionService,
        protected SecurityLogService $securityLog,
    ) {}

    /**
     * Read and validate the sheet without saving anything.
     */
    public function preview(string $path): SheetReadResultData
    {
        return $this->reader->read($path);
    }

    /**
     * How many rows of the sheet match an existing candidate (would be updated) or not (would be added).
     *
     * @return array{existing: int, new: int}
     */
    public function compare(Competition $competition, SheetReadResultData $sheet): array
    {
        $pool = $this->existingByKey($competition);
        $existing = 0;

        foreach ($sheet->rows as $row)
        {
            $key = $this->key(CandidateData::fromObject((object) $row)->all());
            if (!empty($pool[$key]))
            {
                array_shift($pool[$key]);
                $existing++;
            }
        }

        return ['existing' => $existing, 'new' => count($sheet->rows) - $existing];
    }

    /**
     * Import the sheet into a competition and log the operation.
     */
    public function import(Competition $competition, string $path, string $fileName, string $mode = self::MODE_REPLACE, ?int $userId = null): ResultImport
    {
        $sheet = $this->reader->read($path);

        $rows = array_map(
            fn (array $row) => $this->withoutEmpty(CandidateData::fromObject((object) $row, ['competition' => $competition])->all()),
            $sheet->rows
        );

        $replaced = $mode === self::MODE_REPLACE ? $competition->candidates()->count() : 0;

        $this->securityLog->info('import.started', [
            'competition_id' => $competition->id,
            'file_name'      => $fileName,
            'mode'           => $mode,
            'rows'           => count($rows),
            'replaced'       => $replaced,
            'via'            => app()->runningInConsole() ? 'cli' : 'dashboard',
        ]);

        return DB::transaction(function () use ($competition, $sheet, $rows, $fileName, $mode, $userId) {
            $updated = 0;

            if ($mode === self::MODE_REPLACE)
            {
                $this->candidateRepo->deleteForCompetition($competition);
            }
            elseif ($mode === self::MODE_UPSERT)
            {
                [$rows, $updated] = $this->updateExisting($competition, $rows);
            }

            $this->candidateRepo->insertMany($rows);
            $this->candidateRepo->rerank($competition);

            $import = $this->importRepo->store([
                'competition_id' => $competition->id,
                'user_id'        => $userId,
                'file_name'      => $fileName,
                'mode'           => $mode,
                'imported_count' => count($rows),
                'updated_count'  => $updated,
                'skipped_count'  => count($sheet->errors),
                'errors'         => $sheet->errors,
            ]);

            $this->competitionService->flush($competition);

            return $import;
        });
    }

    /**
     * Update candidates that already exist (same governorate + job title + name) and
     * return the rows that are really new, with the number of candidates whose data changed.
     *
     * @return array{0: array, 1: int}
     */
    private function updateExisting(Competition $competition, array $rows): array
    {
        $pool = $this->existingByKey($competition, asModels: true);
        $new = [];
        $updated = 0;

        foreach ($rows as $row)
        {
            /** @var Candidate|null $candidate */
            $key = $this->key($row);
            // اسم مكرر في المجموعة: كل سطر يطابق سجلاً مختلفاً
            $candidate = empty($pool[$key]) ? null : array_shift($pool[$key]);

            if (!$candidate)
            {
                $new[] = $row;
                continue;
            }

            $candidate->fill(array_intersect_key($row, array_flip(self::SHEET_FIELDS)));

            // السطر تغيّر مكانه في الملف فقط: ليس تحديثاً لبيانات الشخص
            $changed = array_diff(array_keys($candidate->getDirty()), ['source_row']);
            if ($candidate->isDirty())
            {
                $candidate->save();
            }
            if ($changed)
            {
                $updated++;
            }
        }

        return [$new, $updated];
    }

    /**
     * Existing candidates grouped by identity key (a list per key, to handle duplicate names).
     *
     * @return array<string, array<int, mixed>>
     */
    private function existingByKey(Competition $competition, bool $asModels = false): array
    {
        $pool = [];
        foreach ($competition->candidates()->orderBy('id')->get() as $candidate)
        {
            $pool[$this->key($candidate->getAttributes())][] = $asModels ? $candidate : true;
        }
        return $pool;
    }

    /**
     * The same person = same governorate + same job title + same (normalized) name.
     */
    private function key(array $row): string
    {
        return $row['governorate'] . '|' . $row['job_title'] . '|' . $row['search_key'];
    }

    /**
     * Keep DB defaults for fields the sheet doesn't provide (bulk insert needs the same keys in every row).
     */
    private function withoutEmpty(array $row): array
    {
        $row['follow_up_status'] ??= 'pending';
        return $row;
    }
}
