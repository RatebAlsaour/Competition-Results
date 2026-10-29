<?php

namespace App\Models;

use App\Interfaces\IHasNullable;
use App\Traits\HasNullable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A language/subject of an exam with its own date, time, place and halls.
 */
class ExamSession extends Model implements IHasNullable
{
    use HasNullable;

    protected $fillable = [
        'exam_id',
        'name',
        'exam_date',
        'start_time',
        'end_time',
        'location',
        'notes',
        'sort_order',
    ];

    protected $nullable = ['exam_date', 'start_time', 'end_time', 'location', 'notes'];

    protected $casts = [
        'exam_date' => 'date',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function halls(): HasMany
    {
        return $this->hasMany(ExamHall::class)->orderBy('from_number');
    }

    public function examinees(): HasMany
    {
        return $this->hasMany(Examinee::class);
    }

    /**
     * Hall of an exam number according to the halls ranges (null when not distributed yet).
     */
    public function hallFor(int $examNumber): ?string
    {
        $hall = $this->hallModelFor($examNumber);
        return $hall ? trim($hall->name . ($hall->building ? " — {$hall->building}" : '')) : null;
    }

    /**
     * Same as hallFor(), for the public file: the hall name, or [hall, building].
     */
    public function publicHallFor(int $examNumber): string|array|null
    {
        return $this->hallModelFor($examNumber)?->publicValue();
    }

    private function hallModelFor(int $examNumber): ?ExamHall
    {
        foreach ($this->halls as $hall)
        {
            if ($examNumber >= $hall->from_number && $examNumber <= $hall->to_number)
            {
                return $hall;
            }
        }
        return null;
    }
}
