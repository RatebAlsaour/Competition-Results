<?php

namespace App\Models;

use App\Enum\CompetitionStatusEnum;
use App\Interfaces\IHasNullable;
use App\Traits\HasNullable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model implements IHasNullable
{
    use HasNullable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'instructions',
        'status',
        'results_published',
        'published_at',
    ];

    protected $nullable = ['description', 'instructions', 'published_at'];

    protected $casts = [
        'status'            => CompetitionStatusEnum::class,
        'results_published' => 'boolean',
        'published_at'      => 'datetime',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class)->orderBy('sort_order')->orderBy('id');
    }

    public function examinees(): HasMany
    {
        return $this->hasMany(Examinee::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', CompetitionStatusEnum::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === CompetitionStatusEnum::PUBLISHED;
    }
}
