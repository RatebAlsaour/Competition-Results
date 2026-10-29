<?php

namespace App\Http\Repositories;

use App\Http\DTOs\ExamData;
use App\Http\Filters\Competition\CompetitionFilter;
use App\Interfaces\IHasDataTransferObjects;
use App\Interfaces\IHasFilterable;
use App\Interfaces\IHasOrderable;
use App\Interfaces\IHasSearchable;
use App\Models\Exam;

class ExamRepo extends BaseRepo implements IHasSearchable, IHasFilterable, IHasOrderable, IHasDataTransferObjects
{
    // نفس فلتر الحالة (مسودة/منشور/مؤرشف) المستخدم في المسابقات
    public $filtersKeys = [
        'exam' => CompetitionFilter::class,
    ];

    public $searchable = ['title', 'slug'];

    public $orderable = ['created_at.desc'];

    public function __construct()
    {
        parent::__construct(new Exam(), ExamData::class);
    }

    public function publishedList()
    {
        return $this->model->query()->published()->orderByDesc('published_at')->get();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->model->query()
            ->where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }
}
