<?php

namespace App\Http\Classes;

use App\Enum\ExamResultEnum;
use App\Exceptions\ErrorMsgException;
use App\Http\DTOs\ExamSheetReadResultData;
use App\Interfaces\IExamSheetReader;
use DateTimeInterface;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads examinees from an .xlsx file with one sheet per language, and possibly several
 * tables in the same sheet (title row + header row + rows), like the sworn translators file.
 *
 * - A header row is any row that has a «الاسم» column and a number/file column.
 * - A row with one text cell is a table title (used as the language when the column is empty).
 * - Rows with an exam number but no name are spare numbers and are skipped.
 * - Cells outside the known columns are kept as a private admin note (never published).
 * - A «توزيع القاعات» table (hall + numbers range «1-189») is read as halls.
 */
class ExcelExamSheetReader implements IExamSheetReader
{
    public function read(string $path): ExamSheetReadResultData
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
        $halls = [];
        $hallWarnings = [];
        $placeholders = 0;
        $hasResults = false;
        $maxRows = (int) config('results.max_rows', 100000);

        try
        {
            foreach ($reader->getSheetIterator() as $sheet)
            {
                $sheetName = ArabicNormalizer::clean($sheet->getName());
                $map = null;
                $hallMap = null;
                $title = null;

                foreach ($sheet->getRowIterator() as $line => $row)
                {
                    $cells = array_map(
                        fn ($cell) => $cell instanceof DateTimeInterface ? $cell->format('Y-m-d') : ArabicNormalizer::clean($cell),
                        $row->toArray()
                    );
                    $filled = array_filter($cells, fn ($cell) => $cell !== '');

                    if (!$filled) continue;

                    if ($header = $this->mapHeader($cells))
                    {
                        $map = $header;
                        $hallMap = null;
                        $hasResults = $hasResults || isset($map['result']) || isset($map['score']);
                        continue;
                    }

                    if ($header = $this->mapHallsHeader($cells))
                    {
                        $hallMap = $header;
                        $map = null;
                        continue;
                    }

                    // عنوان جدول (مثل «الاسبانية») — خلية نصية واحدة
                    if (count($filled) === 1 && !is_numeric(reset($filled)))
                    {
                        $title = reset($filled);
                        continue;
                    }

                    $source = "{$sheetName}:{$line}";

                    if ($hallMap !== null)
                    {
                        $hall = $this->parseHall($cells, $hallMap, $source);
                        is_string($hall)
                            ? $hallWarnings[] = ['row' => $source, 'message' => $hall]
                            : $halls[] = $hall;
                        continue;
                    }

                    if ($map === null) continue; // أسطر قبل أول جدول
                    $name = $cells[$map['full_name']] ?? '';

                    if ($name === '')
                    {
                        $placeholders++;
                        continue;
                    }

                    if (count($rows) + count($errors) >= $maxRows)
                    {
                        throw new ErrorMsgException("الملف يتجاوز الحد الأقصى ({$maxRows} سطر)");
                    }

                    $parsed = $this->parseRow($cells, $map, $title ?: $sheetName, $source);
                    is_string($parsed)
                        ? $errors[] = ['row' => $source, 'message' => "{$name}: {$parsed}"]
                        : $rows[] = $parsed;
                }
            }
        }
        finally
        {
            $reader->close();
        }

        if (!$rows && !$errors)
        {
            throw new ErrorMsgException('لم يتم العثور على جدول أسماء في الملف (يجب أن يحتوي عمودي «الاسم» و«الرقم الامتحاني»)');
        }

        return new ExamSheetReadResultData(
            rows: $rows,
            errors: $errors,
            warnings: array_merge($this->duplicates($rows), $hallWarnings),
            placeholders: $placeholders,
            has_results: $hasResults,
            halls: $halls,
        );
    }

    /**
     * Header of a halls table: a hall column + a numbers range (or from/to) column, and no name column.
     *
     * @return array<string, int>|null
     */
    private function mapHallsHeader(array $cells): ?array
    {
        $keys = array_map(fn ($cell) => ArabicNormalizer::searchKey($cell), $cells);
        $map = [];

        foreach (['name', 'building', 'session', 'range', 'from', 'to'] as $field)
        {
            foreach ((array) config("exams.hall_columns.$field") as $needle)
            {
                $needle = ArabicNormalizer::searchKey($needle);
                foreach ($keys as $i => $key)
                {
                    $match = in_array($field, ['from', 'to'], true) ? $key === $needle : str_contains($key, $needle);
                    if ($key !== '' && !in_array($i, $map, true) && $match)
                    {
                        $map[$field] = $i;
                        continue 3;
                    }
                }
            }
        }

        return isset($map['name']) && (isset($map['range']) || isset($map['from'], $map['to'])) ? $map : null;
    }

    /**
     * @return array|string hall, or warning message
     */
    private function parseHall(array $cells, array $map, string $source): array|string
    {
        $get = fn (string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? '') : '';

        $name = $get('name');
        $numbers = isset($map['range'])
            ? $this->integers($get('range'))
            : array_merge(array_slice($this->integers($get('from')), 0, 1), array_slice($this->integers($get('to')), 0, 1));

        if ($name === '')
        {
            return 'سطر في جدول القاعات بلا اسم قاعة — تم تجاهله';
        }
        if (!$numbers)
        {
            return "القاعة «{$name}» بلا مجال أرقام — تم تجاهلها";
        }

        $from = $numbers[0];
        $to = $numbers[1] ?? $numbers[0];
        if ($from > $to)
        {
            return "القاعة «{$name}»: بداية المجال ({$from}) أكبر من نهايته ({$to}) — تم تجاهلها";
        }

        return [
            'name'        => mb_substr($name, 0, 100),
            'building'    => mb_substr($get('building'), 0, 100) ?: null,
            'from_number' => $from,
            'to_number'   => $to,
            'session'     => $get('session') ?: null,
            'source'      => $source,
        ];
    }

    /**
     * «1-189» → [1, 189]، «من 1 -35» → [1, 35].
     *
     * @return int[]
     */
    private function integers(string $text): array
    {
        preg_match_all('/\d+/', $text, $m);
        return array_map('intval', $m[0]);
    }

    /**
     * @return array<string, int>|null column index per field, or null when the row isn't a header
     */
    private function mapHeader(array $cells): ?array
    {
        $keys = array_map(fn ($cell) => ArabicNormalizer::searchKey($cell), $cells);
        $map = [];

        // ترتيب الحقول مهم: «رقم ملف المتسابق» يُحجز قبل «الرقم الامتحاني»
        foreach (['full_name', 'file_number', 'exam_number', 'session', 'result', 'score'] as $field)
        {
            foreach ((array) config("exams.columns.$field") as $needle)
            {
                $needle = ArabicNormalizer::searchKey($needle);
                foreach ($keys as $i => $key)
                {
                    if ($key !== '' && !in_array($i, $map, true) && str_contains($key, $needle))
                    {
                        $map[$field] = $i;
                        continue 3;
                    }
                }
            }
        }

        return isset($map['full_name'], $map['exam_number']) ? $map : null;
    }

    /**
     * @return array|string parsed row, or error message
     */
    private function parseRow(array $cells, array $map, string $fallbackSession, string $source): array|string
    {
        $get = fn (string $field) => isset($map[$field]) ? ($cells[$map[$field]] ?? '') : '';

        $number = $get('exam_number');
        if (!is_numeric($number) || (int) $number <= 0)
        {
            return 'الرقم الامتحاني غير صالح' . ($number !== '' ? " «{$number}»" : ' (فارغ)');
        }

        $session = $get('session') ?: $fallbackSession;
        if ($session === '')
        {
            return 'اللغة غير محددة';
        }

        $resultText = $get('result');
        $result = $resultText !== '' ? ExamResultEnum::fromText($resultText) : null;
        if ($resultText !== '' && !$result)
        {
            return "نتيجة غير معروفة «{$resultText}»";
        }

        // أي خلية خارج الأعمدة المعروفة = ملاحظة داخلية (لا تُنشر)
        $notes = [];
        foreach ($cells as $i => $cell)
        {
            if ($cell !== '' && !in_array($i, $map, true)) $notes[] = $cell;
        }

        // رقم الملف يُنشر للمتقدم. إن كُتبت مكانه ملاحظة («خطأ سجلت بالانكليزي...») فهي داخلية:
        // رقم الملف الحقيقي قصير ويحتوي رقماً («272»، «88 تمديد»، «ت 32»).
        $fileNumber = $get('file_number');
        if ($fileNumber !== '' && (!preg_match('/\d/', $fileNumber) || mb_strlen($fileNumber) > 20))
        {
            array_unshift($notes, "رقم الملف: {$fileNumber}");
            $fileNumber = '';
        }

        $score = $get('score');

        return [
            'session'     => $session,
            'exam_number' => (int) $number,
            'file_number' => $fileNumber ?: null,
            'full_name'   => $cells[$map['full_name']],
            'admin_note'  => $notes ? implode(' — ', $notes) : null,
            'result'      => $result?->value,
            'score'       => is_numeric($score) ? (float) $score : null,
            'source'      => $source,
        ];
    }

    /**
     * Same exam number twice in one language (imported, but must be reviewed).
     */
    private function duplicates(array $rows): array
    {
        $seen = [];
        $warnings = [];
        foreach ($rows as $row)
        {
            $key = $row['session'] . '|' . $row['exam_number'];
            if (isset($seen[$key]))
            {
                $warnings[] = [
                    'row'     => $row['source'],
                    'message' => "رقم امتحاني مكرر {$row['exam_number']} في «{$row['session']}»: {$row['full_name']} و {$seen[$key]}",
                ];
            }
            $seen[$key] ??= $row['full_name'];
        }
        return $warnings;
    }
}
