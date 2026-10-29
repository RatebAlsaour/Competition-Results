<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Exam */
class ExamResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'instructions'      => $this->instructions,
            'status'            => $this->status->value,
            'status_label'      => $this->status->label(),
            'results_published' => (bool) $this->results_published,
            'published_at'      => $this->published_at?->toDateTimeString(),
            'created_at'        => $this->created_at?->toDateTimeString(),
            'examinees_count'   => $this->whenCounted('examinees'),
            'sessions_count'    => $this->whenCounted('sessions'),
        ];
    }
}
