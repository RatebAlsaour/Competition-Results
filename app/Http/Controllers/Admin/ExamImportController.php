<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ImportExamRequest;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ExamImportService;
use App\Http\Services\ExamTemplateService;
use App\Models\Exam;

class ExamImportController extends Controller
{
    public function __construct(
        protected ExamImportService $importService
    ) {}

    /**
     * Download an Excel template: "results" (pre-filled with the exam's examinees) or "examinees" (empty).
     */
    public function template(Exam $exam, string $type, ExamTemplateService $templates)
    {
        $path = $type === ExamImportService::TYPE_RESULTS ? $templates->results($exam) : $templates->examinees();

        return response()
            ->download($path, "{$type}-template-{$exam->slug}.xlsx")
            ->deleteFileAfterSend();
    }

    /**
     * Validate the file and return a summary without saving.
     */
    public function preview(ImportExamRequest $request, Exam $exam)
    {
        $sheet = $this->importService->preview($request->file('file')->getRealPath());

        $compare = $this->importService->compare($exam, $sheet);

        return ApiResponseService::successResponse([
            'valid_count'    => count($sheet->rows),
            'existing_count' => $compare['existing'],
            'new_count'      => $compare['new'],
            'skipped_count' => count($sheet->errors),
            'placeholders'  => $sheet->placeholders,
            'has_results'   => $sheet->has_results,
            'halls'         => $sheet->halls,
            'halls_coverage' => (object) $sheet->hallsCoverage(),
            'summary'       => $sheet->summary(),
            'errors'        => array_slice($sheet->errors, 0, 200),
            'warnings'      => array_slice($sheet->warnings, 0, 200),
            'current_count' => $exam->examinees()->count(),
        ]);
    }

    /**
     * type=examinees: import names and exam numbers. type=results: set results of existing examinees.
     */
    public function store(ImportExamRequest $request, Exam $exam)
    {
        $path = $request->file('file')->getRealPath();
        $name = $request->file('file')->getClientOriginalName();

        if ($request->input('type') === ExamImportService::TYPE_RESULTS)
        {
            $result = $this->importService->importResults($exam, $path, $name);

            return ApiResponseService::successResponse(
                ['updated' => $result['updated'], 'unmatched' => array_slice($result['unmatched'], 0, 200), 'unmatched_count' => count($result['unmatched'])],
                "تم تسجيل نتائج {$result['updated']} متقدماً",
                201
            );
        }

        $mode = $request->input('mode', ExamImportService::MODE_REPLACE);
        $result = $this->importService->importExaminees($exam, $path, $name, $mode, $request->input('halls_session'));

        return ApiResponseService::successResponse(
            $result,
            $mode === ExamImportService::MODE_UPSERT
                ? "تمت إضافة {$result['imported']} وتحديث {$result['updated']} متقدماً"
                : "تم استيراد {$result['imported']} متقدماً في {$result['sessions']} لغة",
            201
        );
    }
}
