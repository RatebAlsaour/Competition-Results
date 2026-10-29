<?php

namespace App\Console\Commands;

use App\Exceptions\ErrorMsgException;
use App\Http\Repositories\ExamRepo;
use App\Http\Services\ExamImportService;
use App\Models\Exam;
use Illuminate\Console\Command;

class ImportExamCommand extends Command
{
    protected $signature = 'exams:import
                            {exam : رقم الامتحان أو الرابط المختصر (slug)}
                            {file : مسار ملف الإكسل (xlsx)}
                            {--results : الملف يحتوي النتائج (يحدّث المتقدمين الموجودين)}
                            {--append : إضافة إلى المتقدمين الحاليين بدلاً من استبدالهم}
                            {--update : تحديث بيانات الموجودين وإضافة غير الموجودين (لا يحذف أحداً)}
                            {--halls= : اللغة التي يُطبَّق عليها جدول «توزيع القاعات» في الملف}
                            {--dry-run : فحص الملف دون حفظ}
                            {--force : دون طلب تأكيد}';

    protected $description = 'استيراد أسماء المتقدمين وأرقامهم الامتحانية (أو النتائج) لامتحان من ملف Excel';

    public function handle(ExamImportService $importService, ExamRepo $examRepo): int
    {
        $key = $this->argument('exam');
        /** @var Exam|null $exam */
        $exam = $examRepo->query()->where('slug', $key)->orWhere('id', $key)->first();
        if (!$exam)
        {
            $this->error('الامتحان غير موجود');
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

        $this->info("الامتحان: {$exam->title}");
        $this->info('أسماء صالحة: ' . count($sheet->rows) . " | أرقام بلا اسم (متجاهلة): {$sheet->placeholders}");
        $this->table(['اللغة', 'العدد', 'ناجح', 'راسب', 'غائب'], $sheet->summary());

        foreach ([['تحتاج مراجعة', $sheet->warnings], ['أسطر لن تُستورد', $sheet->errors]] as [$title, $list])
        {
            if ($list)
            {
                $this->warn("{$title} (" . count($list) . '):');
                $this->table(['السطر', 'التفاصيل'], $list);
            }
        }

        if ($this->option('dry-run'))
        {
            $this->info('فحص فقط — لم يتم حفظ أي شيء.');
            return self::SUCCESS;
        }

        if ($this->option('results'))
        {
            if (!$sheet->has_results)
            {
                $this->error('الملف لا يحتوي عمود «النتيجة» أو «العلامة».');
                return self::FAILURE;
            }

            $result = $importService->importResults($exam, $file, basename($file));
            $this->info("تم تسجيل نتائج {$result['updated']} متقدماً.");
            if ($result['unmatched'])
            {
                $this->warn('لم يُعثر على أصحابها (' . count($result['unmatched']) . '):');
                $this->table(['السطر', 'التفاصيل'], $result['unmatched']);
            }
            return self::SUCCESS;
        }

        $mode = match (true) {
            (bool) $this->option('update') => ExamImportService::MODE_UPSERT,
            (bool) $this->option('append') => ExamImportService::MODE_APPEND,
            default                        => ExamImportService::MODE_REPLACE,
        };
        $current = $exam->examinees()->count();

        if (!$this->option('force') && $current && $mode === ExamImportService::MODE_REPLACE
            && !$this->confirm("سيتم استبدال {$current} متقدماً حالياً في هذا الامتحان. متابعة؟", true))
        {
            return self::FAILURE;
        }

        if ($sheet->halls && !$this->option('halls'))
        {
            $this->warn('الملف يحتوي جدول توزيع قاعات (' . count($sheet->halls) . ' قاعة) — لتطبيقه أضف: --halls="اسم اللغة"');
        }

        $result = $importService->importExaminees($exam, $file, basename($file), $mode, $this->option('halls'));
        if ($result['halls'])
        {
            $this->info("تم حفظ {$result['halls']} قاعة.");
        }
        $this->info($mode === ExamImportService::MODE_UPSERT
            ? "تمت إضافة {$result['imported']} وتحديث {$result['updated']} متقدماً ({$result['unchanged']} بلا تغيير)."
            : "تم استيراد {$result['imported']} متقدماً في {$result['sessions']} لغة.");

        return self::SUCCESS;
    }
}
