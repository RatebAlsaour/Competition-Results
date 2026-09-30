<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Competition */
class PublicCompetitionResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'slug'               => $this->slug,
            'title'              => $this->title,
            'description'        => $this->description,
            'required_documents' => $this->required_documents ?? [],
            'primary_note'       => $this->primary_note,
            'rankedByScore'      => $this->ranking_method->value === 'score',
            'published_at'       => $this->published_at?->toDateString(),
        ];
    }
}
