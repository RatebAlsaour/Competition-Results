<?php

namespace App\Http\Classes;

use App\Enum\CandidateStatusEnum;
use App\Exceptions\ErrorMsgException;
use App\Http\DTOs\SheetReadResultData;
use App\Interfaces\IResultsSheetReader;
use Carbon\Carbon;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads the first sheet of an .xlsx file. Columns are matched by header text (config/results.php).
 */
class ExcelResultsSheetReader implements IResultsSheetReader
{
    private const REQUIRED = ['full_name', 'governorate', 'job_title', 'status'];

    public function read(string $path): SheetReadResultData
    {
        if (!is_file($path))
        {
            throw new ErrorMsgException('الملف غير موجود');
        }

        $reader = new Reader();
        try
        {
            $reader->open($path);
        }
        catch (\Throwable)
        {
            throw new ErrorMsgException('تعذر قراءة الملف، تأكد أنه ملف Excel بصيغة xlsx');
        }

        $rows = [];
        $errors = [];

        try
        {
            foreach ($reader->getSheetIterator() as $sheet)
            {
                $map = null;
                foreach ($sheet->getRowIterator() as $line => $row)
                {
                    $cells = $row->toArray();

                    if ($map === null)
                    {
                        if ($this->isEmpty($cells)) continue;
                        $map = $this->mapHeader($cells);
                        continue;
                    }

                    if ($this->isEmpty($cells)) continue;

                    $parsed = $this->parseRow($cells, $map, $line);
                    is_string($parsed)
                        ? $errors[] = ['row' => $line, 'message' => $parsed]
                        : $rows[] = $parsed;
                }
                break; // الورقة الأولى فقط
            }
        }
        finally
        {
            $reader->close();
        }

        if (!$rows && !$errors)
        {
            throw new ErrorMsgException('لم يتم العثور على أي بيانات في الملف');
        }

        return new SheetReadResultData(rows: $rows, errors: $errors);
    }

    private function mapHeader(array $cells): array
    {
        $map = [];
        foreach (config('results.columns') as $field => $needles)
        {
            foreach ($cells as $i => $title)
            {
                $title = ArabicNormalizer::searchKey($title);
                foreach ((array) $needles as $needle)
                {
                    if ($title !== '' && str_contains($title, ArabicNormalizer::searchKey($needle)))
                    {
                        $map[$field] = $i;
                        continue 3;
                    }
                }
            }
        }

        $missing = array_diff(self::REQUIRED, array_keys($map));
        if ($missing)
        {
            throw new ErrorMsgException('أعمدة مفقودة في الملف: ' . implode('، ', array_map(
                fn ($field) => ((array) config("results.columns.$field"))[0], $missing
            )));
        }

        return $map;
    }

    /**
     * @return array|string parsed row, or error message
     */
    private function parseRow(array $cells, array $map, int $line): array|string
    {
        $get = fn (string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? null) : null;

        $name = ArabicNormalizer::clean($get('full_name'));
        $governorate = ArabicNormalizer::clean($get('governorate'));
        $jobTitle = ArabicNormalizer::clean($get('job_title'));
        $statusText = ArabicNormalizer::clean($get('status'));

        if ($name === '')        return 'الاسم فارغ';
        if ($governorate === '') return 'المحافظة فارغة';
        if ($jobTitle === '')    return 'المسمى الوظيفي فارغ';

        $status = CandidateStatusEnum::fromText($statusText);
        if (!$status)
        {
            return "حالة غير معروفة: «{$statusText}»";
        }

        $score = $get('score');

        return [
            'full_name'      => $name,
            'governorate'    => $governorate,
            'job_title'      => $jobTitle,
            'status'         => $status->value,
            'score'          => is_numeric($score) ? (float) $score : null,
            'interview_date' => $this->parseDate($get('interview_date')),
            'source_row'     => $line,
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface)
        {
            return $value->format('Y-m-d');
        }

        $value = ArabicNormalizer::clean($value);
        if ($value === '') return null;

        foreach (['j/n/Y', 'd/m/Y', 'Y-m-d', 'j-n-Y'] as $format)
        {
            try
            {
                return Carbon::createFromFormat('!' . $format, $value)->format('Y-m-d');
            }
            catch (\Throwable)
            {
            }
        }
        return null;
    }

    private function isEmpty(array $cells): bool
    {
        foreach ($cells as $cell)
        {
            if ($cell instanceof DateTimeInterface || ArabicNormalizer::clean($cell) !== '') return false;
        }
        return true;
    }
}
