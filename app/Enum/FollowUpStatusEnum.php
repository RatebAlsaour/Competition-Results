<?php

namespace App\Enum;

/**
 * Candidate follow-up after results are published (dashboard only, never public).
 */
enum FollowUpStatusEnum: string
{
    case PENDING             = 'pending';
    case DOCUMENTS_SUBMITTED = 'documents_submitted';
    case CONTRACTED          = 'contracted';
    case WITHDRAWN           = 'withdrawn';
    case NO_SHOW             = 'no_show';

    public function label(): string
    {
        return match($this)
        {
            self::PENDING             => 'بانتظار المراجعة',
            self::DOCUMENTS_SUBMITTED => 'قدّم الأوراق',
            self::CONTRACTED          => 'تم التعاقد',
            self::WITHDRAWN           => 'اعتذر',
            self::NO_SHOW             => 'لم يراجع',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
