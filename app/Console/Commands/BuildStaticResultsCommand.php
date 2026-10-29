<?php

namespace App\Console\Commands;

use App\Http\Services\ExamSnapshotService;
use App\Http\Services\ResultsSnapshotService;
use Illuminate\Console\Command;

class BuildStaticResultsCommand extends Command
{
    protected $signature = 'results:build-static';

    protected $description = 'إعادة توليد ملفات النتائج الثابتة (public/data) لكل المسابقات المنشورة — نفّذه بعد كل نشر للكود';

    public function handle(ResultsSnapshotService $snapshot, ExamSnapshotService $examSnapshot): int
    {
        $started = microtime(true);
        $count = $snapshot->refreshAll();
        $exams = $examSnapshot->refreshAll();

        $this->info(sprintf('تم توليد ملفات %d مسابقة و %d امتحان منشور خلال %.2f ثانية.', $count, $exams, microtime(true) - $started));

        return self::SUCCESS;
    }
}
