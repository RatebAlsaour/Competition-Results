<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultImport extends Model
{
    protected $fillable = [
        'competition_id',
        'user_id',
        'file_name',
        'mode',
        'imported_count',
        'skipped_count',
        'errors',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
