<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamHall extends Model
{
    /**
     * What the examinee sees: the hall name, or [hall, building] when the building is set.
     */
    public function publicValue(): string|array
    {
        return $this->building ? [$this->name, $this->building] : $this->name;
    }

    protected $fillable = [
        'exam_session_id',
        'name',
        'building',
        'from_number',
        'to_number',
    ];

    protected $casts = [
        'from_number' => 'integer',
        'to_number'   => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
