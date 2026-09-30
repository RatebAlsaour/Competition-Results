<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreCandidateRequest;
use App\Http\Requests\Candidate\UpdateCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\CandidateService;
use App\Http\Services\CandidatesExportService;
use App\Models\Candidate;
use App\Models\Competition;

class CandidateController extends Controller
{
    public function __construct(
        protected CandidateService $candidateService
    ) {}

    /**
     * Display a listing of the resource (search-key, filters[candidate][...], max, page).
     */
    public function index(Competition $competition)
    {
        return ApiResponseService::successResponse(CandidateResource::collection($this->candidateService->paginate($competition)));
    }

    public function options(Competition $competition)
    {
        return ApiResponseService::successResponse($this->candidateService->filterOptions($competition));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCandidateRequest $request, Competition $competition)
    {
        $candidate = $this->candidateService->create($request, $competition);
        return ApiResponseService::successResponse(CandidateResource::make($candidate), transResponse('added'), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCandidateRequest $request, Candidate $candidate)
    {
        $candidate = $this->candidateService->update($request, $candidate);
        return ApiResponseService::successResponse(CandidateResource::make($candidate));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Candidate $candidate)
    {
        $this->candidateService->delete($candidate);
        return ApiResponseService::deletedResponse();
    }

    public function export(Competition $competition, CandidatesExportService $exportService)
    {
        return response()
            ->download($exportService->export($competition), "candidates-{$competition->slug}.xlsx")
            ->deleteFileAfterSend();
    }
}
