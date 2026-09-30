<?php

namespace App\Http\Services;

use App\Enum\CandidateStatusEnum;
use App\Http\Repositories\CandidateRepo;
use App\Http\Repositories\CompetitionRepo;
use App\Http\Resources\PublicCompetitionResource;
use App\Models\Competition;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Publishes results as static JSON files so public traffic never reaches PHP or the database.
 *
 * Layout (on the "results_static" disk = public/data):
 *   competitions.json                  published competitions + current version path   (short cache)
 *   {slug}/{version}/index.json        governorates → job titles → result file         (immutable)
 *   {slug}/{version}/r/{n}.json        ranked candidates of one (governorate + job title) (immutable)
 *
 * Every rebuild writes a new version folder, then swaps competitions.json,
 * so visitors never read a half-written update.
 */
class ResultsSnapshotService
{
    public const INDEX_FILE = 'competitions.json';

    public function __construct(
        protected CompetitionRepo $competitionRepo,
        protected CandidateRepo $candidateRepo,
    ) {}

    /**
     * Rebuild the public files of a competition (or remove them when it's not published).
     */
    public function refresh(Competition $competition): void
    {
        Cache::lock('results-snapshot', 60)->block(30, function () use ($competition) {
            if ($competition->exists && $competition->isPublished())
            {
                $this->writeCompetition($competition);
            }
            $this->writeIndex();
        });
    }

    /**
     * Rebuild everything (after deploy, or if public/data was deleted).
     */
    public function refreshAll(): int
    {
        return Cache::lock('results-snapshot', 120)->block(60, function () {
            $published = $this->competitionRepo->publishedList();
            foreach ($published as $competition)
            {
                $this->writeCompetition($competition);
            }
            $this->writeIndex();
            return $published->count();
        });
    }

    private function writeCompetition(Competition $competition): void
    {
        $version = now()->format('YmdHis') . Str::lower(Str::random(4));
        $dir = "{$competition->slug}/{$version}";
        $groups = $this->candidateRepo->publicRowsGrouped($competition);

        $governorates = [];
        $names = ['groups' => [], 'rows' => []];
        $file = 0;
        foreach ($this->candidateRepo->governorates($competition) as $governorate)
        {
            $titles = [];
            foreach ($this->candidateRepo->jobTitles($competition, $governorate) as $jobTitle)
            {
                $rows = $groups[$governorate . '|' . $jobTitle] ?? [];
                $group = $file;
                $path = 'r/' . $file++ . '.json';

                $this->put("{$dir}/{$path}", $this->resultsPayload($governorate, $jobTitle, $rows));
                $titles[] = ['name' => $jobTitle, 'file' => $path, 'total' => count($rows)];

                // فهرس البحث المباشر بالاسم: [الاسم، رقم المجموعة، الترتيب، 1=أساسي/0=احتياطي]
                $names['groups'][] = [$governorate, $jobTitle];
                foreach ($rows as $row)
                {
                    $names['rows'][] = [$row->full_name, $group, (int) $row->rank, $row->status === CandidateStatusEnum::PRIMARY->value ? 1 : 0];
                }
            }
            $governorates[] = ['name' => $governorate, 'titles' => $titles];
        }

        $this->put("{$dir}/names.json", $names);
        $this->put("{$dir}/index.json", ['governorates' => $governorates]);

        $competition->forceFill(['public_version' => $version])->saveQuietly();
        $this->pruneVersions($competition->slug, $version);
    }

    /**
     * Same shape as the public API results endpoint.
     */
    private function resultsPayload(string $governorate, string $jobTitle, array $rows): array
    {
        $primary = 0;
        $candidates = array_map(function ($row) use (&$primary) {
            if ($row->status === CandidateStatusEnum::PRIMARY->value) $primary++;
            return ['seq' => (int) $row->rank, 'name' => $row->full_name, 'status' => $row->status];
        }, $rows);

        return [
            'governorate'  => $governorate,
            'jobTitle'     => $jobTitle,
            'total'        => count($candidates),
            'primaryCount' => $primary,
            'reserveCount' => count($candidates) - $primary,
            'candidates'   => $candidates,
        ];
    }

    /**
     * competitions.json — written last and atomically (temp file + rename).
     * Also removes folders of competitions that are no longer published.
     */
    private function writeIndex(): void
    {
        $published = $this->competitionRepo->publishedList()->filter(fn (Competition $c) => $c->public_version);

        $list = $published->map(fn (Competition $c) => array_merge(
            (new PublicCompetitionResource($c))->resolve(),
            ['path' => "{$c->slug}/{$c->public_version}"]
        ))->values()->all();

        $tmp = self::INDEX_FILE . '.' . Str::random(8) . '.tmp';
        $this->put($tmp, $list);
        $this->disk()->move($tmp, self::INDEX_FILE);

        $keep = $published->pluck('slug')->all();
        foreach ($this->disk()->directories() as $slug)
        {
            if (!in_array($slug, $keep, true))
            {
                $this->disk()->deleteDirectory($slug);
            }
        }

        $this->ensureHtaccess();
    }

    private function pruneVersions(string $slug, string $current): void
    {
        $versions = collect($this->disk()->directories($slug))
            ->map(fn ($d) => basename($d))
            ->reject(fn ($v) => $v === $current)
            ->sortDesc()
            ->values();

        // الإبقاء على نسخ سابقة لمن فتح الصفحة قبل التحديث
        foreach ($versions->slice(max(0, (int) config('results.static.keep_versions') - 1)) as $old)
        {
            $this->disk()->deleteDirectory("{$slug}/{$old}");
        }
    }

    /**
     * Cache headers for Apache (ignored by Nginx; see deploy/nginx.conf.example).
     */
    private function ensureHtaccess(): void
    {
        if ($this->disk()->exists('.htaccess')) return;

        $this->disk()->put('.htaccess', implode("\n", [
            '<IfModule mod_headers.c>',
            '    Header set Cache-Control "public, max-age=31536000, immutable"',
            '    <Files "competitions.json">',
            '        Header set Cache-Control "public, max-age=30, must-revalidate"',
            '    </Files>',
            '</IfModule>',
            'AddType application/json .json',
            '<IfModule mod_deflate.c>',
            '    AddOutputFilterByType DEFLATE application/json',
            '</IfModule>',
            '',
        ]));
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
