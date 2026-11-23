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
        'building',
        'seat_row',
        'seat_number',
        'admin_note',
        'result',
        'score',
        'result_note',
    ];

    protected $nullable = ['file_number', 'hall_name', 'building', 'seat_row', 'seat_number', 'admin_note', 'result', 'score', 'result_note'];

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
        if ($this->hall_name)
        {
            return $this->hall_name . ($this->building ? " — {$this->building}" : '');
        }
        return $this->session?->hallFor($this->exam_number);
    }

    /**
     * For the public file: [hall, building, row, seat] without the empty tail, or just the hall name.
     */
    public function publicHall(): string|array|null
    {
        $hall = $this->hall_name
            ? [$this->hall_name, $this->building]
            : (array) $this->session?->publicHallFor($this->exam_number);

        $value = array_pad(array_values($hall), 2, null);
        array_push($value, $this->seat_row, $this->seat_number);

        while ($value && end($value) === null) array_pop($value);
        return count($value) > 1 ? $value : ($value[0] ?? null);
    }
}
