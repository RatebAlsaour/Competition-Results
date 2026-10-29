<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreExamineeRequest;
use App\Http\Requests\Exam\UpdateExamineeRequest;
use App\Http\Resources\ExamineeResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ExamineeService;
use App\Models\Exam;
use App\Models\Examinee;

class ExamineeController extends Controller
{
    public function __construct(
        protected ExamineeService $examineeService
    ) {}

    /**
     * Display a listing of the resource (search-key, filters[examinee][...], max, page).
     */
    public function index(Exam $exam)
    {
        return ApiResponseService::successResponse(ExamineeResource::collection($this->examineeService->paginate($exam)));
    }

    public function options(Exam $exam)
    {
        return ApiResponseService::successResponse($this->examineeService->options($exam));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExamineeRequest $request, Exam $exam)
    {
        $examinee = $this->examineeService->create($request, $exam);
        return ApiResponseService::successResponse(ExamineeResource::make($examinee), transResponse('added'), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExamineeRequest $request, Examinee $examinee)
    {
        $examinee = $this->examineeService->update($request, $examinee);
        return ApiResponseService::successResponse(ExamineeResource::make($examinee));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Examinee $examinee)
    {
        $this->examineeService->delete($examinee);
        return ApiResponseService::deletedResponse();
    }
}
