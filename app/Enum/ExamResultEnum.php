<?php

namespace App\Enum;

enum ExamResultEnum: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';
    case ABSENT = 'absent';

    public function label(): string
    {
        return match($this)
        {
            self::PASSED => 'ناجح',
            self::FAILED => 'راسب',
            self::ABSENT => 'غائب',
        };
    }

    /**
     * Detect result from free text in the sheet (ex: "ناجح", "راسبة", "غياب").
     */
    public static function fromText(string $text): ?self
    {
        foreach (config('exams.result_keywords') as $result => $keywords)
        {
            foreach ((array) $keywords as $keyword)
            {
                if ($keyword !== '' && str_contains($text, $keyword))
                {
                    return self::from($result);
                }
            }
        }
        return null;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
