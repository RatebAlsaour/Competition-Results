<?php

namespace App\Http\Resources;

use App\Traits\ResourcesPagination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Examinee */
class ExamineeResource extends JsonResource
{
    use ResourcesPagination;

    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'exam_id'         => $this->exam_id,
            'exam_session_id' => $this->exam_session_id,
            'session'         => $this->session?->name,
            'exam_number'     => $this->exam_number,
            'file_number'     => $this->file_number,
            'full_name'       => $this->full_name,
            'hall'            => $this->hall(),          // القاعة الفعلية (خاصة أو من التوزيع)
            'hall_name'       => $this->hall_name,       // القاعة الخاصة فقط
            'admin_note'      => $this->admin_note,
            'result'          => $this->result?->value,
            'result_label'    => $this->result?->label(),
            'score'           => $this->score,
            'result_note'     => $this->result_note,
            'updated_at'      => $this->updated_at?->toDateTimeString(),
        ];
    }
}
