<?php

namespace App\Http\Services;

use App\Http\Repositories\ExamSessionRepo;
use App\Models\Exam;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel templates that the import (ExcelExamSheetReader) reads back as-is:
 *
 *  - results:   every examinee already filled in (one sheet per language); staff only type
 *               «العلامة» and «النتيجة», then upload the same file.
 *  - examinees: empty sheet with the right headers, for a new exam.
 */
class ExamTemplateService
{
    public const HEADERS_EXAMINEES = ['الرقم الامتحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة'];
    public const HEADERS_RESULTS   = ['الرقم الامتحاني', 'رقم ملف المتسابق', 'الاسم الثلاثي', 'اللغة', 'العلامة', 'النتيجة'];

    public function __construct(
        protected ExamSessionRepo $sessionRepo,
    ) {}

    /**
     * @return string path of the generated temp file
     */
    public function results(Exam $exam): string
    {
        [$writer, $path] = $this->open();

        $this->instructions($writer->getCurrentSheet(), $writer, [
            'قالب إدخال نتائج: ' . $exam->title,
            'في كل ورقة لغة: اكتب في عمود «النتيجة» إحدى الكلمات: ناجح أو راسب أو غائب.',
            'عمود «العلامة» اختياري.',
            'لا تغيّر عمودي «الرقم الامتحاني» و«اللغة»: بهما يُعرف صاحب النتيجة.',
            'يمكن رفع الملف قبل اكتماله: الأسطر التي لا نتيجة فيها ولا علامة تُتجاهل، ويمكن إكمالها ورفع الملف مجدداً.',
            'بعد الانتهاء: لوحة التحكم ← الامتحان ← استيراد Excel ← النتائج.',
            'النتائج لا تظهر للعموم إلا بعد الضغط على «نشر النتائج».',
        ]);

        $names = ['تعليمات'];
        foreach ($this->sessionRepo->forExam($exam) as $session)
        {
            $examinees = $session->examinees()->orderBy('exam_number')->get(['exam_number', 'file_number', 'full_name', 'result', 'score']);
            if ($examinees->isEmpty()) continue;

            $sheet = $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($names[] = $this->sheetName($session->name, $names));
            $this->format($sheet, [16, 20, 38, 18, 12, 14]);

            $writer->addRow(Row::fromValuesWithStyle(self::HEADERS_RESULTS, $this->headerStyle()));
            foreach ($examinees as $e)
            {
                $writer->addRow(Row::fromValues([
                    $e->exam_number,
                    $e->file_number ?? '',
                    $e->full_name,
                    $session->name,
                    $e->score ?? '',
                    $e->result?->label() ?? '',   // النتائج المدخلة سابقاً تبقى، فيُكمل الموظف من حيث توقف
                ]));
            }
        }

        $writer->close();
        return $path;
    }

    /**
     * @return string path of the generated temp file
     */
    public function examinees(): string
    {
        [$writer, $path] = $this->open();

        $this->instructions($writer->getCurrentSheet(), $writer, [
            'قالب أسماء المتقدمين وأرقامهم الامتحانية',
            'املأ ورقة «الأسماء»: سطر لكل متقدم.',
            'الأعمدة المطلوبة: الرقم الامتحاني، الاسم الثلاثي، اللغة. رقم ملف المتسابق اختياري.',
            'الرقم الامتحاني يبدأ من 1 لكل لغة.',
            'يمكن وضع كل لغة في ورقة مستقلة بنفس العناوين.',
            'أي عمود إضافي تكتبه (ملاحظات) يُحفظ ملاحظة داخلية ولا يُنشر.',
        ]);

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName('الأسماء');
        $this->format($sheet, [16, 20, 38, 18]);
        $writer->addRow(Row::fromValuesWithStyle(self::HEADERS_EXAMINEES, $this->headerStyle()));

        $writer->close();
        return $path;
    }

    private function open(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'exam-template') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);

        return [$writer, $path];
    }

    /**
     * First sheet: one line per row in a single cell, so the reader treats them as titles and skips them.
     */
    private function instructions(Sheet $sheet, Writer $writer, array $lines): void
    {
        $sheet->setName('تعليمات');
        $sheet->setSheetView((new SheetView())->withRightToLeft(true));
        $sheet->setColumnWidth(110, 1);

        foreach ($lines as $i => $line)
        {
            $writer->addRow(Row::fromValuesWithStyle([$line], (new Style())->withFontBold($i === 0)->withFontSize($i === 0 ? 14 : 12)));
        }
    }

    /**
     * Right-to-left sheet, fixed header row, readable column widths.
     */
    private function format(Sheet $sheet, array $widths): void
    {
        $sheet->setSheetView((new SheetView())->withRightToLeft(true)->withFreezeRow(2));
        foreach ($widths as $i => $width)
        {
            $sheet->setColumnWidth($width, $i + 1);
        }
    }

    private function headerStyle(): Style
    {
        return (new Style())
            ->withFontBold(true)
            ->withFontColor(Color::rgb(237, 235, 224))
            ->withBackgroundColor(Color::rgb(0, 38, 35));
    }

    /**
     * Excel sheet names: max 31 chars, none of \ / ? * : [ ], unique in the workbook.
     */
    private function sheetName(string $name, array $used): string
    {
        $base = mb_substr(trim(preg_replace('/[\\\\\/\?\*\:\[\]\']/u', ' ', $name)) ?: 'لغة', 0, 28);
        $candidate = $base;
        $i = 2;
        while (in_array($candidate, $used, true))
        {
            $candidate = $base . ' ' . $i++;
        }
        return $candidate;
    }
}
