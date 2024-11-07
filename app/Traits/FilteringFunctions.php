<?php

namespace App\Traits;

use App\Http\Factories\FilterFactory;

trait FilteringFunctions
{
    /**
     * Base filter function
     * Calls the filter method if filters are present in the request.
     *
     * @param mixed $query The query builder instance.
     * @param array|null $just filter just keys passed.
     * @param array|null $except filter all except keys passed.
     * @return mixed The modified query instance with filters applied.
     */
    public function applyFilter($query, $just = null, $except = null)
    {
        $filters = request()->input(config('filter.request_filters_key'));
        if($filters)
        {
            return $query = $this->filter($filters, $just, $except);
        }
        return $query; // Return original query if no filters are present
    }

    /**
     * Applies filters to a query based on the provided filters.
     *
     * @param array $filters The filters to apply to the query.
     * @param array|null $just filter just keys passed.
     * @param array|null $except filter all except keys passed.
     * @return mixed The modified query instance with filters applied.
     */
    public function filter($filters, $just = null, $except = null)
    {
        // Create a query instance
        $query = $this->model->query();

        // Apply filters
        foreach ($filters as $key => $value)
        {
            if(isset($just))
            {
                if(!in_array($key, $just)) continue;
            }
            if(isset($except))
            {
                if(in_array($key, $except)) continue;
            }
            $filterClass = FilterFactory::create($key, $value, $this);
            $filterClass->apply($query);
        }

        // Return the filtered query result
        return $query;
    }
}
