<?php

namespace App\Models;

use App\Enum\CompetitionStatusEnum;
use App\Enum\RankingMethodEnum;
use App\Interfaces\IHasNullable;
use App\Traits\HasNullable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model implements IHasNullable
{
    use HasNullable;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
        'ranking_method',
        'required_documents',
        'primary_note',
        'published_at',
    ];

    protected $nullable = ['description', 'primary_note', 'published_at'];

    protected $casts = [
        'status'             => CompetitionStatusEnum::class,
        'ranking_method'     => RankingMethodEnum::class,
        'required_documents' => 'array',
        'published_at'       => 'datetime',
    ];

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(ResultImport::class);
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
