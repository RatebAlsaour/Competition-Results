<?php

namespace App\Models;

use App\Enum\CandidateStatusEnum;
use App\Enum\FollowUpStatusEnum;
use App\Interfaces\IHasNullable;
use App\Traits\HasNullable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model implements IHasNullable
{
    use HasNullable;

    protected $fillable = [
        'competition_id',
        'full_name',
        'search_key',
        'governorate',
        'job_title',
        'interview_date',
        'score',
        'status',
        'rank',
        'source_row',
        'follow_up_status',
        'notes',
    ];

    protected $nullable = ['interview_date', 'score', 'notes'];

    protected $casts = [
        'interview_date'   => 'date',
        'score'            => 'float',
        'status'           => CandidateStatusEnum::class,
        'follow_up_status' => FollowUpStatusEnum::class,
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}
