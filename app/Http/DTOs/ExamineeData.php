<?php

namespace App\Http\DTOs;

use App\Http\Classes\ArabicNormalizer;
use App\Models\Examinee;

class ExamineeData extends BaseDTO
{
    public ?int $exam_id = null;

    public ?int $exam_session_id = null;

    public ?int $exam_number = null;

    public ?string $file_number = null;

    public ?string $full_name = null;

    public ?string $search_key = null;

    public ?string $hall_name = null;

    public ?string $admin_note = null;

    public ?string $result = null;

    public ?float $score = null;

    public ?string $result_note = null;

    /**
     * @param mixed $object Request | object (sheet row)
     * @param ...$args ['exam' => Exam, 'session' => ExamSession] on create, ['examinee' => Examinee] on update
     */
    public static function fromObject($object, ...$args): self
    {
        /** @var Examinee|null $current */
        $current = self::getArg('examinee', $args);
        $exam = self::getArg('exam', $args);
        $session = self::getArg('session', $args);

        $fullName = self::pick($object, 'full_name', $current?->full_name);
        $fullName = $fullName !== null ? ArabicNormalizer::clean($fullName) : null;

        $score = self::pick($object, 'score', $current?->score);
        $result = self::pick($object, 'result', $current?->result);
        $number = self::pick($object, 'exam_number', $current?->exam_number);

        return new self([
            'exam_id'         => $exam?->id ?? $current?->exam_id,
            'exam_session_id' => $session?->id ?? self::pick($object, 'exam_session_id', $current?->exam_session_id),
            'exam_number'     => is_numeric($number) ? (int) $number : null,
            'file_number'     => self::clean(self::pick($object, 'file_number', $current?->file_number)),
            'full_name'       => $fullName,
            'search_key'      => $fullName !== null ? ArabicNormalizer::searchKey($fullName) : null,
            'hall_name'       => self::clean(self::pick($object, 'hall_name', $current?->hall_name)),
            'admin_note'      => self::clean(self::pick($object, 'admin_note', $current?->admin_note)),
            'result'          => ($result instanceof \BackedEnum ? $result->value : $result) ?: null,
            'score'           => is_numeric($score) ? (float) $score : null,
            'result_note'     => self::clean(self::pick($object, 'result_note', $current?->result_note)),
        ]);
    }

    private static function clean(mixed $value): ?string
    {
        $value = ArabicNormalizer::clean($value);
        return $value === '' ? null : $value;
    }
}
