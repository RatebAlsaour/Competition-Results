<?php

namespace App\Console\Commands;

use App\Exceptions\ErrorMsgException;
use App\Http\Repositories\CompetitionRepo;
use App\Http\Services\ResultsImportService;
use App\Models\Competition;
use Illuminate\Console\Command;

class ImportResultsCommand extends Command
{
    protected $signature = 'results:import
                            {competition : رقم المسابقة أو الرابط المختصر (slug)}
                            {file : مسار ملف الإكسل (xlsx)}
                            {--append : إضافة إلى النتائج الحالية بدلاً من استبدالها}
                            {--update : تحديث بيانات الموجودين وإضافة غير الموجودين (لا يحذف أحداً)}
                            {--dry-run : فحص الملف دون حفظ}
                            {--force : دون طلب تأكيد}';

    protected $description = 'استيراد نتائج مسابقة من ملف Excel';

    public function handle(ResultsImportService $importService, CompetitionRepo $competitionRepo): int
    {
        $key = $this->argument('competition');
        /** @var Competition|null $competition */
        $competition = $competitionRepo->query()->where('slug', $key)->orWhere('id', $key)->first();
        if (!$competition)
        {
            $this->error('المسابقة غير موجودة');
            return self::FAILURE;
        }

        $file = $this->argument('file');

        try
        {
            $sheet = $importService->preview($file);
        }
        catch (ErrorMsgException $e)
        {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("المسابقة: {$competition->title}");
        $this->info('عدد السجلات الصالحة: ' . count($sheet->rows));
        $this->table(['المحافظة', 'المسميات', 'أساسي', 'احتياطي', 'المجموع'], $sheet->summary());

        if ($sheet->errors)
        {
            $this->warn('أسطر تم تجاهلها (' . count($sheet->errors) . '):');
            $this->table(['السطر', 'السبب'], $sheet->errors);
        }

        if ($this->option('dry-run'))
        {
            $this->info('فحص فقط — لم يتم حفظ أي شيء.');
            return self::SUCCESS;
        }

        $mode = match (true) {
            (bool) $this->option('update') => ResultsImportService::MODE_UPSERT,
            (bool) $this->option('append') => ResultsImportService::MODE_APPEND,
            default                        => ResultsImportService::MODE_REPLACE,
        };
        $current = $competition->candidates()->count();

        if (!$this->option('force') && $current && $mode === ResultsImportService::MODE_REPLACE
            && !$this->confirm("سيتم استبدال {$current} سجلاً حالياً في هذه المسابقة. متابعة؟", true))
        {
            return self::FAILURE;
        }

        $import = $importService->import($competition, $file, basename($file), $mode);
        $this->info($mode === ResultsImportService::MODE_UPSERT
            ? "تمت إضافة {$import->imported_count} وتحديث {$import->updated_count} سجلاً."
            : "تم استيراد {$import->imported_count} سجلاً بنجاح.");

        return self::SUCCESS;
    }
}
