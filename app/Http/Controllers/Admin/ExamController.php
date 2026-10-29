<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ExamService;
use App\Models\Exam;

class ExamController extends Controller
{
    public function __construct(
        protected ExamService $examService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return ApiResponseService::successResponse(ExamResource::collection($this->examService->list()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExamRequest $request)
    {
        $exam = $this->examService->create($request);
        return ApiResponseService::successResponse(ExamResource::make($exam), transResponse('added'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        return ApiResponseService::successResponse(ExamResource::make($this->examService->show($exam)));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExamRequest $request, Exam $exam)
    {
        $exam = $this->examService->update($request, $exam);
        return ApiResponseService::successResponse(ExamResource::make($this->examService->show($exam)));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam)
    {
        $this->examService->delete($exam);
        return ApiResponseService::deletedResponse();
    }

    public function stats(Exam $exam)
    {
        return ApiResponseService::successResponse($this->examService->stats($exam));
    }
}
