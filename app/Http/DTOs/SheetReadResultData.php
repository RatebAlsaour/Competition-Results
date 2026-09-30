<?php

namespace App\Http\DTOs;

class SheetReadResultData extends BaseDTO
{
    /** @var array<int, array<string, mixed>> الأسطر الصالحة */
    public array $rows = [];

    /** @var array<int, array{row: int, message: string}> الأسطر المتجاهلة */
    public array $errors = [];

    public static function fromObject($object, ...$args): self
    {
        return new self([
            'rows'   => $object->rows ?? [],
            'errors' => $object->errors ?? [],
        ]);
    }

    /**
     * Count per governorate: [{governorate, job_titles, primary, reserve, total}]
     */
    public function summary(): array
    {
        return collect($this->rows)
            ->groupBy('governorate')
            ->map(fn ($rows, $governorate) => [
                'governorate' => $governorate,
                'job_titles'  => $rows->pluck('job_title')->unique()->count(),
                'primary'     => $rows->where('status', 'primary')->count(),
                'reserve'     => $rows->where('status', 'reserve')->count(),
                'total'       => $rows->count(),
            ])
            ->values()
            ->all();
    }
}
