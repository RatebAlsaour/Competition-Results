<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ResultImport */
class ResultImportResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'file_name'      => $this->file_name,
            'mode'           => $this->mode,
            'imported_count' => $this->imported_count,
            'skipped_count'  => $this->skipped_count,
            'errors'         => $this->errors ?? [],
            'user'           => $this->user?->name,
            'created_at'     => $this->created_at?->toDateTimeString(),
        ];
    }
}
