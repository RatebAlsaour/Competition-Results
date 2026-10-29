<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CandidateController;
use App\Http\Controllers\Admin\CompetitionController;
use App\Http\Controllers\Admin\ExamController;
use App\Http\Controllers\Admin\ExamImportController;
use App\Http\Controllers\Admin\ExamineeController;
use App\Http\Controllers\Admin\ExamSessionController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\ResultsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public results API (optional — the portal itself reads static files from public/data)
|--------------------------------------------------------------------------
| Limit is generous: mobile carriers put many users behind one IP (CGNAT).
*/
Route::middleware('throttle:600,1')->prefix('competitions')->controller(ResultsController::class)->group(function () {
    Route::get('/', 'competitions');
    Route::get('{slug}', 'competition');
    Route::get('{slug}/governorates', 'governorates');
    Route::get('{slug}/job-titles', 'jobTitles');
    Route::get('{slug}/results', 'results');
});

/*
|--------------------------------------------------------------------------
| Dashboard (session auth)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware('web')->group(function () {

    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::apiResource('competitions', CompetitionController::class);
        Route::get('competitions/{competition}/stats', [CompetitionController::class, 'stats']);

        Route::get('competitions/{competition}/candidates', [CandidateController::class, 'index']);
        Route::get('competitions/{competition}/candidates/options', [CandidateController::class, 'options']);
        Route::get('competitions/{competition}/candidates/export', [CandidateController::class, 'export']);
        Route::post('competitions/{competition}/candidates', [CandidateController::class, 'store']);
        Route::put('candidates/{candidate}', [CandidateController::class, 'update']);
        Route::delete('candidates/{candidate}', [CandidateController::class, 'destroy']);

        Route::get('competitions/{competition}/imports', [ImportController::class, 'index']);
        Route::post('competitions/{competition}/imports/preview', [ImportController::class, 'preview']);
        Route::post('competitions/{competition}/imports', [ImportController::class, 'store']);

        // ---- الامتحانات (مثال: التراجمة المحلفين) ----
        Route::apiResource('exams', ExamController::class);
        Route::get('exams/{exam}/stats', [ExamController::class, 'stats']);

        Route::get('exams/{exam}/sessions', [ExamSessionController::class, 'index']);
        Route::put('exam-sessions/{session}', [ExamSessionController::class, 'update']);
        Route::post('exam-sessions/{session}/copy-to-all', [ExamSessionController::class, 'copyToAll']);
        Route::put('exam-sessions/{session}/halls', [ExamSessionController::class, 'halls']);

        Route::get('exams/{exam}/examinees', [ExamineeController::class, 'index']);
        Route::get('exams/{exam}/examinees/options', [ExamineeController::class, 'options']);
        Route::post('exams/{exam}/examinees', [ExamineeController::class, 'store']);
        Route::put('examinees/{examinee}', [ExamineeController::class, 'update']);
        Route::delete('examinees/{examinee}', [ExamineeController::class, 'destroy']);

        Route::get('exams/{exam}/templates/{type}', [ExamImportController::class, 'template'])->whereIn('type', ['results', 'examinees']);
        Route::post('exams/{exam}/imports/preview', [ExamImportController::class, 'preview']);
        Route::post('exams/{exam}/imports', [ExamImportController::class, 'store']);
    });
});
