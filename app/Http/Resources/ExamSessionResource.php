<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ExamSession */
class ExamSessionResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'exam_date'       => $this->exam_date?->format('Y-m-d'),
            'start_time'      => $this->start_time,
            'end_time'        => $this->end_time,
            'location'        => $this->location,
            'notes'           => $this->notes,
            'sort_order'      => $this->sort_order,
            'examinees_count' => $this->whenCounted('examinees'),
            'max_exam_number' => (int) ($this->examinees_max_exam_number ?? 0),
            'halls'           => $this->whenLoaded('halls', fn () => $this->halls->map(fn ($h) => [
                'id'          => $h->id,
                'name'        => $h->name,
                'building'    => $h->building,
                'from_number' => $h->from_number,
                'to_number'   => $h->to_number,
            ])->all()),
        ];
    }
}
