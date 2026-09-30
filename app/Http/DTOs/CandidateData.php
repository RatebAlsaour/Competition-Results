<?php

namespace App\Http\DTOs;

use App\Http\Classes\ArabicNormalizer;
use App\Models\Candidate;

class CandidateData extends BaseDTO
{
    public ?int $competition_id = null;

    public ?string $full_name = null;

    public ?string $search_key = null;

    public ?string $governorate = null;

    public ?string $job_title = null;

    public ?string $interview_date = null;

    public ?float $score = null;

    public ?string $status = null;

    public ?int $source_row = null;

    public ?string $follow_up_status = null;

    public ?string $notes = null;

    /**
     * @param mixed $object Request | object (sheet row)
     * @param ...$args ['competition' => Competition] on create, ['candidate' => Candidate] on update
     */
    public static function fromObject($object, ...$args): self
    {
        /** @var Candidate|null $current */
        $current = self::getArg('candidate', $args);
        $competition = self::getArg('competition', $args);

        $fullName = self::pick($object, 'full_name', $current?->full_name);
        $fullName = $fullName !== null ? ArabicNormalizer::clean($fullName) : null;

        $score = self::pick($object, 'score', $current?->score);
        $date = self::pick($object, 'interview_date', $current?->interview_date);

        return new self([
            'competition_id'   => $competition?->id ?? $current?->competition_id,
            'full_name'        => $fullName,
            'search_key'       => $fullName !== null ? ArabicNormalizer::searchKey($fullName) : null,
            'governorate'      => self::cleanOrNull(self::pick($object, 'governorate', $current?->governorate)),
            'job_title'        => self::cleanOrNull(self::pick($object, 'job_title', $current?->job_title)),
            'interview_date'   => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : ($date ?: null),
            'score'            => is_numeric($score) ? (float) $score : null,
            'status'           => self::enumValue(self::pick($object, 'status', $current?->status)),
            'source_row'       => self::pick($object, 'source_row', $current?->source_row),
            'follow_up_status' => self::enumValue(self::pick($object, 'follow_up_status', $current?->follow_up_status)),
            'notes'            => self::cleanOrNull(self::pick($object, 'notes', $current?->notes)),
        ]);
    }

    private static function cleanOrNull(mixed $value): ?string
    {
        $value = ArabicNormalizer::clean($value);
        return $value === '' ? null : $value;
    }

    private static function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
