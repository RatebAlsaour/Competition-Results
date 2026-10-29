<?php

namespace App\Http\DTOs;

use App\Http\Classes\ArabicNormalizer;
use App\Models\ExamSession;

class ExamSessionData extends BaseDTO
{
    public ?int $exam_id = null;

    public ?string $name = null;

    public ?string $exam_date = null;

    public ?string $start_time = null;

    public ?string $end_time = null;

    public ?string $location = null;

    public ?string $notes = null;

    public ?int $sort_order = null;

    /**
     * @param mixed $object
     * @param ...$args ['exam' => Exam] on create, ['session' => ExamSession] on update
     */
    public static function fromObject($object, ...$args): self
    {
        /** @var ExamSession|null $current */
        $current = self::getArg('session', $args);
        $exam = self::getArg('exam', $args);

        $date = self::pick($object, 'exam_date', $current?->exam_date);

        return new self([
            'exam_id'    => $exam?->id ?? $current?->exam_id,
            'name'       => self::clean(self::pick($object, 'name', $current?->name)) ?: $current?->name,
            'exam_date'  => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : ($date ?: null),
            'start_time' => self::time(self::pick($object, 'start_time', $current?->start_time)),
            'end_time'   => self::time(self::pick($object, 'end_time', $current?->end_time)),
            'location'   => self::clean(self::pick($object, 'location', $current?->location)),
            'notes'      => self::pick($object, 'notes', $current?->notes) ?: null,
            'sort_order' => self::pick($object, 'sort_order', $current?->sort_order),
        ]);
    }

    private static function clean(mixed $value): ?string
    {
        $value = ArabicNormalizer::clean($value);
        return $value === '' ? null : $value;
    }

    /**
     * "9:5" / "09:05:00" → "09:05"
     */
    private static function time(mixed $value): ?string
    {
        if (!$value || !preg_match('/^(\d{1,2}):(\d{1,2})/', (string) $value, $m))
        {
            return null;
        }
        return sprintf('%02d:%02d', $m[1], $m[2]);
    }
}
