<?php

namespace App\Enum;

enum CompetitionStatusEnum: string
{
    case DRAFT     = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED  = 'archived';

    public function label(): string
    {
        return match($this)
        {
            self::DRAFT     => 'مسودة',
            self::PUBLISHED => 'منشورة',
            self::ARCHIVED  => 'مؤرشفة',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
