<?php

namespace App\Http\Services;

use App\Http\DTOs\CandidateData;
use App\Http\DTOs\SheetReadResultData;
use App\Http\Repositories\CandidateRepo;
use App\Http\Repositories\ResultImportRepo;
use App\Interfaces\IResultsSheetReader;
use App\Models\Competition;
use App\Models\ResultImport;
use Illuminate\Support\Facades\DB;

class ResultsImportService
{
    public const MODE_REPLACE = 'replace';
    public const MODE_APPEND  = 'append';

    public function __construct(
        protected IResultsSheetReader $reader,
        protected CandidateRepo $candidateRepo,
        protected ResultImportRepo $importRepo,
        protected CompetitionService $competitionService,
    ) {}

    /**
     * Read and validate the sheet without saving anything.
     */
    public function preview(string $path): SheetReadResultData
    {
        return $this->reader->read($path);
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

        return DB::transaction(function () use ($competition, $sheet, $rows, $fileName, $mode, $userId) {
            if ($mode === self::MODE_REPLACE)
            {
                $this->candidateRepo->deleteForCompetition($competition);
            }

            $this->candidateRepo->insertMany($rows);
            $this->candidateRepo->rerank($competition);

            $import = $this->importRepo->store([
                'competition_id' => $competition->id,
                'user_id'        => $userId,
                'file_name'      => $fileName,
                'mode'           => $mode,
                'imported_count' => count($rows),
                'skipped_count'  => count($sheet->errors),
                'errors'         => $sheet->errors,
            ]);

            $this->competitionService->flush($competition);

            return $import;
        });
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
