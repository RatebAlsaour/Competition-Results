<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Candidate */
class CandidateResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'competition_id'         => $this->competition_id,
            'rank'                   => $this->rank,
            'full_name'              => $this->full_name,
            'governorate'            => $this->governorate,
            'job_title'              => $this->job_title,
            'interview_date'         => $this->interview_date?->format('Y-m-d'),
            'score'                  => $this->score,
            'status'                 => $this->status->value,
            'status_label'           => $this->status->label(),
            'follow_up_status'       => $this->follow_up_status->value,
            'follow_up_status_label' => $this->follow_up_status->label(),
            'notes'                  => $this->notes,
            'source_row'             => $this->source_row,
            'updated_at'             => $this->updated_at?->toDateTimeString(),
        ];
    }
}
