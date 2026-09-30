<?php

namespace App\Enum;

/**
 * How candidates are ranked inside each (governorate + job title) group.
 */
enum RankingMethodEnum: string
{
    case SCORE = 'score';   // العلامة الأعلى أولاً ثم ترتيب الملف
    case SHEET = 'sheet';   // ترتيب الأسطر كما في ملف الإكسل

    public function label(): string
    {
        return match($this)
        {
            self::SCORE => 'حسب العلامة الأعلى',
            self::SHEET => 'حسب ترتيب الملف',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
