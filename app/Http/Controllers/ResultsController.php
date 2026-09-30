<?php

namespace App\Http\Controllers;

use App\Http\Requests\Results\JobTitlesRequest;
use App\Http\Requests\Results\ResultsRequest;
use App\Http\Resources\PublicCompetitionResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ResultsService;
use Illuminate\Http\JsonResponse;

/**
 * Public read-only endpoints for published competitions.
 */
class ResultsController extends Controller
{
    public function __construct(
        protected ResultsService $resultsService
    ) {}

    public function competitions(): JsonResponse
    {
        return $this->cached(ApiResponseService::successResponse(
            PublicCompetitionResource::collection($this->resultsService->competitions())
        ));
    }

    public function competition(string $slug): JsonResponse
    {
        return $this->cached(ApiResponseService::successResponse(
            PublicCompetitionResource::make($this->resultsService->competition($slug))
        ));
    }

    public function governorates(string $slug): JsonResponse
    {
        $competition = $this->resultsService->competition($slug);
        return $this->cached(ApiResponseService::successResponse($this->resultsService->governorates($competition)));
    }

    public function jobTitles(JobTitlesRequest $request, string $slug): JsonResponse
    {
        $competition = $this->resultsService->competition($slug);
        return $this->cached(ApiResponseService::successResponse(
            $this->resultsService->jobTitles($competition, $request->governorate)
        ));
    }

    public function results(ResultsRequest $request, string $slug): JsonResponse
    {
        $competition = $this->resultsService->competition($slug);
        return $this->cached(ApiResponseService::successResponse(
            $this->resultsService->results($competition, $request->governorate, $request->job_title)
        ));
    }

    private function cached(JsonResponse $response): JsonResponse
    {
        return $response->setPublic()->setMaxAge((int) config('results.http_cache_seconds'));
    }
}
