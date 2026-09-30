<?php

namespace App\Http\DTOs;

use App\Enum\CompetitionStatusEnum;
use App\Models\Competition;

class CompetitionData extends BaseDTO
{
    public ?string $title = null;

    public ?string $slug = null;

    public ?string $description = null;

    public ?string $status = null;

    public ?string $ranking_method = null;

    public ?array $required_documents = null;

    public ?string $primary_note = null;

    public ?string $published_at = null;

    /**
     * @param mixed $object
     * @param ...$args ['competition' => Competition] on update
     */
    public static function fromObject($object, ...$args): self
    {
        /** @var Competition|null $current */
        $current = self::getArg('competition', $args);

        $status = self::pick($object, 'status', $current?->status?->value);

        // تاريخ النشر يُسجل عند أول نشر فقط
        $publishedAt = $current?->published_at?->toDateTimeString();
        if ($status === CompetitionStatusEnum::PUBLISHED->value && !$publishedAt)
        {
            $publishedAt = now()->toDateTimeString();
        }

        return new self([
            'title'              => self::pick($object, 'title', $current?->title) ?: $current?->title,
            'slug'               => self::pick($object, 'slug', $current?->slug) ?: $current?->slug,
            'description'        => self::pick($object, 'description', $current?->description),
            'status'             => $status,
            'ranking_method'     => self::pick($object, 'ranking_method', $current?->ranking_method?->value),
            'required_documents' => self::documents(self::pick($object, 'required_documents', $current?->required_documents)),
            'primary_note'       => self::pick($object, 'primary_note', $current?->primary_note),
            'published_at'       => $publishedAt,
        ]);
    }

    /**
     * Normalize documents list to [{title, notes: []}] and drop empty rows.
     */
    private static function documents(?array $documents): ?array
    {
        if ($documents === null)
        {
            return null;
        }

        return array_values(array_filter(array_map(fn ($doc) => [
            'title' => trim((string) ($doc['title'] ?? '')),
            'notes' => array_values(array_filter(array_map('trim', (array) ($doc['notes'] ?? [])))),
        ], $documents), fn ($doc) => $doc['title'] !== ''));
    }
}
