<?php

namespace App\Http\Services;

use App\Http\Repositories\ExamineeRepo;
use App\Http\Repositories\ExamRepo;
use App\Http\Repositories\ExamSessionRepo;
use App\Models\Exam;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publishes exams as static JSON files (same idea as ResultsSnapshotService):
 *
 *   exams/index.json                 published exams + current version path        (short cache)
 *   exams/{slug}/{version}/names.json  sessions (date, time, place) + every examinee  (immutable)
 *
 * names.json rows: [name, sessionIndex, examNumber, fileNumber, hall] — hall is a name, or [hall, building]
 * and, only when the exam's results are published: [..., result, score, resultNote].
 * Admin notes are never written here, nor the fields hidden in the exam settings (hidden_fields).
 */
class ExamSnapshotService
{
    public const DIR = 'exams';
    public const INDEX_FILE = self::DIR . '/index.json';

    public function __construct(
        protected ExamRepo $examRepo,
        protected ExamSessionRepo $sessionRepo,
        protected ExamineeRepo $examineeRepo,
    ) {}

    public function refresh(Exam $exam): void
    {
        Cache::lock('results-snapshot', 60)->block(30, function () use ($exam) {
            if ($exam->exists && $exam->isPublished())
            {
                $this->writeExam($exam);
            }
            $this->writeIndex();
        });
    }

    public function refreshAll(): int
    {
        return Cache::lock('results-snapshot', 120)->block(60, function () {
            $published = $this->examRepo->publishedList();
            foreach ($published as $exam)
            {
                $this->writeExam($exam);
            }
            $this->writeIndex();
            return $published->count();
        });
    }

    private function writeExam(Exam $exam): void
    {
        $version = now()->format('YmdHis') . Str::lower(Str::random(4));
        $dir = self::DIR . "/{$exam->slug}/{$version}";

        $sessions = $this->sessionRepo->forExam($exam);
        $index = $sessions->pluck('id')->flip();   // session id → position
        $byId = $sessions->keyBy('id');

        $rows = [];
        foreach ($this->examineeRepo->publicRows($exam) as $e)
        {
            $session = $byId[$e->exam_session_id] ?? null;
            if (!$session) continue;

            $row = [
                $e->full_name,
                $index[$e->exam_session_id],
                $e->exam_number,
                $exam->shows('file_number') ? $e->file_number : null,
                $this->hall($exam, $e->hall_name ?: $session->publicHallFor($e->exam_number)),
            ];

            if ($exam->results_published)
            {
                array_push($row,
                    $exam->shows('result') ? $e->result?->value : null,
                    $exam->shows('score') ? $e->score : null,
                    $exam->shows('result_note') ? $e->result_note : null,
                );
            }

            $rows[] = $row;
        }

        $this->put("{$dir}/names.json", [
            'sessions' => $sessions->map(fn ($s) => [
                'name'     => $s->name,
                'date'     => $exam->shows('date') ? $s->exam_date?->format('Y-m-d') : null,
                'start'    => $exam->shows('time') ? $s->start_time : null,
                'end'      => $exam->shows('time') ? $s->end_time : null,
                'location' => $exam->shows('location') ? $s->location : null,
                'notes'    => $exam->shows('notes') ? $s->notes : null,
            ])->values()->all(),
            'rows' => $rows,
        ]);

        $exam->forceFill(['public_version' => $version])->saveQuietly();
        $this->pruneVersions($exam->slug, $version);
    }

    /**
     * exams/index.json — written last and atomically. Removes folders of unpublished exams.
     */
    private function writeIndex(): void
    {
        $published = $this->examRepo->publishedList()->filter(fn (Exam $e) => $e->public_version);

        $list = $published->map(fn (Exam $e) => [
            'slug'             => $e->slug,
            'title'            => $e->title,
            'description'      => $e->description,
            'instructions'     => $e->shows('instructions') ? $e->instructions : null,
            'resultsPublished' => (bool) $e->results_published,
            'hidden'           => $e->hidden_fields ?? [],
            'path'             => self::DIR . "/{$e->slug}/{$e->public_version}",
        ])->values()->all();

        $tmp = self::DIR . '/index.' . Str::random(8) . '.tmp';
        $this->put($tmp, $list);
        $this->disk()->move($tmp, self::INDEX_FILE);

        $keep = $published->pluck('slug')->all();
        foreach ($this->disk()->directories(self::DIR) as $path)
        {
            if (!in_array(basename($path), $keep, true))
            {
                $this->disk()->deleteDirectory($path);
            }
        }
    }

    /**
     * Hall value without the hidden parts: a name, [name, building], or null.
     */
    private function hall(Exam $exam, string|array|null $value): string|array|null
    {
        [$name, $building] = is_array($value) ? $value : [$value, null];
        $name = $exam->shows('hall') ? $name : null;
        $building = $exam->shows('building') ? $building : null;

        return $building ? [$name, $building] : $name;
    }

    private function pruneVersions(string $slug, string $current): void
    {
        $versions = collect($this->disk()->directories(self::DIR . "/{$slug}"))
            ->map(fn ($d) => basename($d))
            ->reject(fn ($v) => $v === $current)
            ->sortDesc()
            ->values();

        foreach ($versions->slice(max(0, (int) config('results.static.keep_versions') - 1)) as $old)
        {
            $this->disk()->deleteDirectory(self::DIR . "/{$slug}/{$old}");
        }
    }

    private function put(string $path, array $data): void
    {
        $this->disk()->put($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('results.static.disk'));
    }
}
