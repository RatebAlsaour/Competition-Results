<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\StoreCompetitionRequest;
use App\Http\Requests\Competition\UpdateCompetitionRequest;
use App\Http\Resources\CompetitionResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\CompetitionService;
use App\Models\Competition;

class CompetitionController extends Controller
{
    public function __construct(
        protected CompetitionService $competitionService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ApiResponseService::successResponse(CompetitionResource::collection($this->competitionService->list()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetitionRequest $request)
    {
        $competition = $this->competitionService->create($request);
        return ApiResponseService::successResponse(CompetitionResource::make($competition), transResponse('added'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Competition $competition)
    {
        return ApiResponseService::successResponse(CompetitionResource::make($this->competitionService->show($competition)));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetitionRequest $request, Competition $competition)
    {
        $competition = $this->competitionService->update($request, $competition);
        return ApiResponseService::successResponse(CompetitionResource::make($competition));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Competition $competition)
    {
        $this->competitionService->delete($competition);
        return ApiResponseService::deletedResponse();
    }

    public function stats(Competition $competition)
    {
        return ApiResponseService::successResponse($this->competitionService->stats($competition));
    }
}
