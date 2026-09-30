<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Repositories\ResultImportRepo;
use App\Http\Requests\Import\ImportResultsRequest;
use App\Http\Resources\ResultImportResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ResultsImportService;
use App\Models\Competition;

class ImportController extends Controller
{
    public function __construct(
        protected ResultsImportService $importService,
        protected ResultImportRepo $importRepo,
    ) {}

    /**
     * Import history of a competition.
     */
    public function index(Competition $competition)
    {
        return ApiResponseService::successResponse(ResultImportResource::collection($this->importRepo->forCompetition($competition)));
    }

    /**
     * Validate the file and return a summary without saving.
     */
    public function preview(ImportResultsRequest $request, Competition $competition)
    {
        $sheet = $this->importService->preview($request->file('file')->getRealPath());

        return ApiResponseService::successResponse([
            'valid_count'   => count($sheet->rows),
            'skipped_count' => count($sheet->errors),
            'summary'       => $sheet->summary(),
            'errors'        => array_slice($sheet->errors, 0, 200),
            'sample'        => array_slice($sheet->rows, 0, 10),
            'current_count' => $competition->candidates()->count(),
        ]);
    }

    public function store(ImportResultsRequest $request, Competition $competition)
    {
        $import = $this->importService->import(
            $competition,
            $request->file('file')->getRealPath(),
            $request->file('file')->getClientOriginalName(),
            $request->input('mode', ResultsImportService::MODE_REPLACE),
            $request->user()?->id,
        );

        return ApiResponseService::successResponse(
            ResultImportResource::make($import->load('user')),
            "تم استيراد {$import->imported_count} سجلاً بنجاح",
            201
        );
    }
}
