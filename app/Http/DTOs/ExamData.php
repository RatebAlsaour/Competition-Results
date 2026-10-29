<?php

namespace App\Http\DTOs;

use App\Enum\CompetitionStatusEnum;
use App\Models\Exam;

class ExamData extends BaseDTO
{
    public ?string $title = null;

    public ?string $slug = null;

    public ?string $description = null;

    public ?string $instructions = null;

    public ?string $status = null;

    public ?bool $results_published = null;

    public ?string $published_at = null;

    /**
     * @param mixed $object
     * @param ...$args ['exam' => Exam] on update
     */
    public static function fromObject($object, ...$args): self
    {
        /** @var Exam|null $current */
        $current = self::getArg('exam', $args);

        $status = self::pick($object, 'status', $current?->status?->value);

        // تاريخ النشر يُسجل عند أول نشر فقط
        $publishedAt = $current?->published_at?->toDateTimeString();
        if ($status === CompetitionStatusEnum::PUBLISHED->value && !$publishedAt)
        {
            $publishedAt = now()->toDateTimeString();
        }

        $resultsPublished = self::pick($object, 'results_published', $current?->results_published);

        return new self([
            'title'             => self::pick($object, 'title', $current?->title) ?: $current?->title,
            'slug'              => self::pick($object, 'slug', $current?->slug) ?: $current?->slug,
            'description'       => self::pick($object, 'description', $current?->description),
            'instructions'      => self::pick($object, 'instructions', $current?->instructions),
            'status'            => $status,
            'results_published' => $resultsPublished === null ? null : filter_var($resultsPublished, FILTER_VALIDATE_BOOLEAN),
            'published_at'      => $publishedAt,
        ]);
    }
}
