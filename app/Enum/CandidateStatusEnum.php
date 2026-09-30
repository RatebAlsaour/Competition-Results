<?php

namespace App\Enum;

enum CandidateStatusEnum: string
{
    case PRIMARY = 'primary';
    case RESERVE = 'reserve';

    public function label(): string
    {
        return match($this)
        {
            self::PRIMARY => 'أساسي',
            self::RESERVE => 'احتياطي',
        };
    }

    /**
     * Detect status from free text in the sheet (ex: "تعاقد أساسي", "ناجح احتياط").
     */
    public static function fromText(string $text): ?self
    {
        foreach (config('results.status_keywords') as $status => $keywords)
        {
            foreach ((array) $keywords as $keyword)
            {
                if ($keyword !== '' && str_contains($text, $keyword))
                {
                    return self::from($status);
                }
            }
        }
        return null;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
