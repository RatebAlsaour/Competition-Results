<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Competition */
class CompetitionResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'description'        => $this->description,
            'status'             => $this->status->value,
            'status_label'       => $this->status->label(),
            'ranking_method'     => $this->ranking_method->value,
            'required_documents' => $this->required_documents ?? [],
            'primary_note'       => $this->primary_note,
            'published_at'       => $this->published_at?->toDateTimeString(),
            'created_at'         => $this->created_at?->toDateTimeString(),
            'candidates_count'   => $this->whenCounted('candidates'),
            'primary_count'      => $this->whenHas('primary_count'),
            'reserve_count'      => $this->whenHas('reserve_count'),
        ];
    }
}
