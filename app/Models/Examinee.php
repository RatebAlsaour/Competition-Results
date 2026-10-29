<?php

namespace App\Models;

use App\Enum\ExamResultEnum;
use App\Interfaces\IHasNullable;
use App\Traits\HasNullable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Examinee extends Model implements IHasNullable
{
    use HasNullable;

    protected $fillable = [
        'exam_id',
        'exam_session_id',
        'exam_number',
        'file_number',
        'full_name',
        'search_key',
        'hall_name',
        'admin_note',
        'result',
        'score',
        'result_note',
    ];

    protected $nullable = ['file_number', 'hall_name', 'admin_note', 'result', 'score', 'result_note'];

    protected $casts = [
        'exam_number' => 'integer',
        'score'       => 'float',
        'result'      => ExamResultEnum::class,
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    /**
     * Special hall (ex: ground floor) or the hall from the session's distribution.
     */
    public function hall(): ?string
    {
        return $this->hall_name ?: $this->session?->hallFor($this->exam_number);
    }
}
