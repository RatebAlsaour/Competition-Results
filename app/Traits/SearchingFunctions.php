<?php

namespace App\Traits;

use Closure;
use Illuminate\Support\Facades\DB;

trait SearchingFunctions
{
    /**
     * Base search function
     */
    public function applySearch(&$query)
    {
        if(config('search.search_enabled'))
        {
            $searchValue = request()->input(config('search.request_search_key'));
            if($searchValue)
            {
                $word = explode('*', $searchValue);
                foreach ($word as $value)
                {
                    $query->where(function ($query) use ($value) {

                        // Apply normal search
                        $this->applyNormalSearch($query, $value);

                        // Apply search to concatenated field
                        // $this->applyConcatenateSearch($query, $value);
                    });
                }
            }
            if(config('search.search_limit_enabled'))
            {
                return $query->limit(config('search.default_search_limit'));
            }
        }
        return $query;
    }

    /**
     * Apply search to a specific field, handling nested relationships.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $field
     * @param mixed $searchValue
     * @return void
     */
    protected function applySearchToField($query, $field, $searchValue, $isLastItem = false)
    {
        $nestedFields = explode('.', $field);

        if (count($nestedFields) > 1)
        {
            // Handle nested relationships
            $this->applyNestedSearch($query, $nestedFields, $searchValue);
        }
        else
        {
            // Regular field
            if ($isLastItem)
            {
                $query->where($field, 'LIKE', '%' . $searchValue . '%');
            }
            else
            {
                $query->orWhere($field, 'LIKE', '%' . $searchValue . '%');
            }
        }
    }

    /**
     * Apply search to a nested relationship field.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $nestedFields
     * @param mixed $searchValue
     * @return void
     */
    protected function applyNestedSearch($query, $nestedFields, $searchValue)
    {
        $relationship = array_shift($nestedFields);
        $isLastItem = count($nestedFields) > 1 ? false : true;
        if ($isLastItem)
        {
            if(in_array($relationship, array_keys($this->morphs ?? [])))
            {
                foreach($this->morphs[$relationship] as $morphTypeEnum)
                {
                    $query->orWhereHasMorph($relationship, $morphTypeEnum->namespace(), function ($query) use ($nestedFields, $searchValue, $isLastItem) {
                        $this->applySearchToField($query, implode('.', $nestedFields), $searchValue, $isLastItem);
                    });
                }
            }
            else
            {
                $query->whereHas($relationship, function ($query) use ($nestedFields, $searchValue, $isLastItem) {
                    $this->applySearchToField($query, implode('.', $nestedFields), $searchValue, $isLastItem);
                });
            }
        }
        else
        {
            if(in_array($relationship, array_keys($this->morphs ?? [] )))
            {
                foreach($this->morphs[$relationship] as $morphTypeEnum)
                {
                    $query->orWhereHasMorph($relationship, $morphTypeEnum->namespace(), function ($query) use ($nestedFields, $searchValue, $isLastItem) {
                        $this->applySearchToField($query, implode('.', $nestedFields), $searchValue, $isLastItem);
                    });
                }
            }
            else
            {
                $query->orWhereHas($relationship, function ($query) use ($nestedFields, $searchValue, $isLastItem) {
                    $this->applySearchToField($query, implode('.', $nestedFields), $searchValue, $isLastItem);
                });
            }
        }
    }

    // protected function applyConcatenatedRealationSearch($query, $searchValue)
    // {
    //     foreach ($this->concatFiledRealation as $key => $value) {
    //         $concatenatedField = 'CONCAT(' . implode(', " ", ', $value) . ')';
    //         $query->whereHas($key, function ($sub) use ($concatenatedField, $value, $searchValue) {
    //             $sub->selectRaw("$concatenatedField AS concatenated_field")
    //                 ->whereNotNull(DB::raw('CONCAT(' . implode(', " ", ', array_map(function ($field) {
    //                     return "IFNULL($field, '')";
    //                 }, $value)) . ')'))
    //                 ->whereRaw('CONCAT(' . implode(', " ", ', array_map(function ($field) {
    //                     return "IFNULL($field, '')";
    //                 }, $value)) . ') LIKE ?', ['%' . $searchValue . '%']);
    //         });
    //     }
    // }

    // protected function applyConcatenatedSearch($query, $searchValue)
    // {
    //     foreach ($this->concatFiled as $key => $value) {
    //         $concatenatedField = 'CONCAT(' . implode(', " ", ', $value) . ')';
    //         $query->selectRaw("$concatenatedField AS concatenated_field")
    //             ->whereNotNull(DB::raw('CONCAT(' . implode(', " ", ', array_map(function ($field) {
    //                 return "IFNULL($field, '')";
    //             }, $value)) . ')'))
    //             ->whereRaw('CONCAT(' . implode(', " ", ', array_map(function ($field) {
    //                 return "IFNULL($field, '')";
    //             }, $value)) . ') LIKE ?', ['%' . $searchValue . '%']);
    //     }
    // }

    // protected function applyConcatenatedSearch($query, $searchValue)
    // {
    //     foreach ($this->concatFiled as $key => $value) {
    //         $concatenatedField = 'concat(' . implode(', " ", ', $this->concatFiled[$key]) . ')';
    //         $query->whereRaw("$concatenatedField LIKE ?", ['%' . $searchValue . '%']);
    //     }
    // }


    // Apply normal search according to searchable attribute in model
    protected function applyNormalSearch(&$query, $value)
    {
        $searchableFields = $this->searchable ?? [];
        foreach ($searchableFields as $field)
        {

            $query->orWhere(function ($query) use ($field, $value) {
                $this->applySearchToField($query, $field, $value);
            });
        }
    }

    // Apply search to concatenated field
    // protected function applyConcatenateSearch(&$query, $value)
    // {
    //     if ($this->concatFiledRealation)
    //     {
    //         $query->orWhere(function ($query) use ($value) {
    //             $this->applyConcatenatedRealationSearch($query, $value);
    //         });
    //     }
    //     if ($this->concatFiled)
    //     {
    //         $query->orWhere(function ($query) use ($value) {
    //             $this->applyConcatenatedSearch($query, $value);
    //         });
    //     }
    // }

    /**
     * MUST SEARCH SCOPE
     * Dont return all items if search key is empty
     */
    protected function scopeMustSearch($query, Closure $callback)
    {
        return request()->input('search-key') != null
            ?  $callback($query)
            : $callback($query->limit(config('search.default_search_limit')));
        // return request()->input('search-key') != null
        //     ?  $callback($query)
        //     : new Collection([]);
    }
}
