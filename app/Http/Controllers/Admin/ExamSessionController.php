<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\ExamHallsRequest;
use App\Http\Requests\Exam\UpdateExamSessionRequest;
use App\Http\Resources\ExamSessionResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\ExamSessionService;
use App\Models\Exam;
use App\Models\ExamSession;

class ExamSessionController extends Controller
{
    public function __construct(
        protected ExamSessionService $sessionService
    ) {}

    /**
     * Sessions (languages) of an exam with their schedule and halls.
     */
    public function index(Exam $exam)
    {
        return ApiResponseService::successResponse(ExamSessionResource::collection($this->sessionService->list($exam)));
    }

    /**
     * Update date, time, place and notes of a session.
     */
    public function update(UpdateExamSessionRequest $request, ExamSession $session)
    {
        $session = $this->sessionService->update($request, $session);
        return ApiResponseService::successResponse(ExamSessionResource::make($session));
    }

    /**
     * Copy this session's schedule to every other session of the exam.
     */
    public function copyToAll(ExamSession $session)
    {
        $this->sessionService->copyScheduleToAll($session);
        return ApiResponseService::successResponse(ExamSessionResource::collection($this->sessionService->list($session->exam)));
    }

    /**
     * Generate halls by capacity, or replace them with an explicit list.
     */
    public function halls(ExamHallsRequest $request, ExamSession $session)
    {
        $session = $request->filled('halls')
            ? $this->sessionService->replaceHalls($session, $request->input('halls'))
            : $this->sessionService->generateHalls(
                $session,
                (int) $request->input('capacity'),
                $request->input('prefix') ?: 'القاعة',
                (int) $request->input('start_at', 1),
            );

        return ApiResponseService::successResponse(ExamSessionResource::make($session));
    }
}
