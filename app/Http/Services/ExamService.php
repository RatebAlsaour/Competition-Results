<?php

namespace App\Http\Services;

use App\Http\Repositories\ExamineeRepo;
use App\Http\Repositories\ExamRepo;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExamService
{
    public function __construct(
        protected ExamRepo $examRepo,
        protected ExamineeRepo $examineeRepo,
        protected ResultsCacheService $cache,
        protected ExamSnapshotService $snapshot,
        protected SecurityLogService $securityLog,
    ) {}

    public function list()
    {
        return $this->examRepo->fetch()
            ->withCount(['examinees', 'sessions'])
            ->paginate(request()->input(config('pagination.request_max_key'), 20));
    }

    public function show(Exam $exam): Exam
    {
        return $exam->loadCount(['examinees', 'sessions']);
    }

    public function create(Request $request): Exam
    {
        $request->merge(['slug' => $this->uniqueSlug($request->input('slug') ?: $request->input('title'))]);

        $exam = $this->examRepo->store($request);
        $this->flush($exam);

        // تحميل القيم الافتراضية من قاعدة البيانات (الحالة، نشر النتائج)
        return $exam->refresh();
    }

    public function update(Request $request, Exam $exam): Exam
    {
        if ($request->filled('slug'))
        {
            $request->merge(['slug' => $this->uniqueSlug($request->input('slug'), $exam->id)]);
        }

        $before = ['status' => $exam->status->value, 'results_published' => $exam->results_published];

        $this->examRepo->update($request, $exam, ['exam' => $exam]);
        $this->flush($exam);
        $exam->refresh();

        $this->securityLog->info('exam.updated', [
            'exam_id' => $exam->id,
            'title'   => $exam->title,
            'fields'  => array_keys($request->all()),
            'status'  => $before['status'] !== $exam->status->value ? "{$before['status']} → {$exam->status->value}" : null,
            'results_published' => $before['results_published'] !== $exam->results_published ? $exam->results_published : null,
        ]);

        return $exam;
    }

    public function delete(Exam $exam): void
    {
        $count = $exam->examinees()->count();
        $exam->delete();
        $this->flush($exam);

        $this->securityLog->warning('exam.deleted', ['exam_id' => $exam->id, 'title' => $exam->title, 'examinees' => $count]);
    }

    /**
     * Dashboard statistics — cached until any examinee of the exam changes.
     */
    public function stats(Exam $exam): array
    {
        return $this->cache->remember(self::scope($exam), 'stats', fn () => $this->examineeRepo->stats($exam));
    }

    /**
     * Refresh the dashboard cache and the public static files, after the DB transaction commits.
     */
    public function flush(Exam $exam): void
    {
        DB::afterCommit(function () use ($exam) {
            $this->cache->flush(self::scope($exam));
            $this->snapshot->refresh($exam);
        });
    }

    /**
     * Dashboard-only data changed (ex: a private note): no need to rebuild public files.
     */
    public function flushAdmin(Exam $exam): void
    {
        DB::afterCommit(fn () => $this->cache->flush(self::scope($exam)));
    }

    public static function scope(Exam $exam): string
    {
        return 'exam:' . $exam->id;
    }

    private function uniqueSlug(?string $value, ?int $exceptId = null): string
    {
        $base = Str::slug((string) $value) ?: 'exam-' . Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;
        while ($this->examRepo->slugExists($slug, $exceptId))
        {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
