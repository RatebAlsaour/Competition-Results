<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueWith implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected array $columns,
        protected string $message,
        protected array $morphsColumns = [],
        protected ?int $except = null,
        protected string $connection = 'mysql',
        protected ?string $valuePrefix = '',
        protected ?string $valueSuffix = '',
    ){}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $attribute = array_reverse(explode('.', $attribute))[0];

        $query = DB::connection($this->connection)->table($this->table)
            ->where($attribute, $this->valuePrefix . $value . $this->valueSuffix);

        $query->when(isset($this->except),function($subQuery) {
                return $subQuery->where('id', '!=', $this->except);
            });

        foreach ($this->columns as $column)
        {
            $query->when(in_array($column, $this->morphsColumns), function($query) use ($column) {
                // return $query->where($column, MorphTypeEnum::from(request()->{$column})->namespace());
            })->when(!in_array($column, $this->morphsColumns), function($query) use ($column){
                return $query->where($column, request()->{$column});
            });
        }

        if($query->exists())
        {
            $fail($this->message);
        }
    }
}
