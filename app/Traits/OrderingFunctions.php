<?php

namespace App\Traits;

trait OrderingFunctions
{
    /**
     * Base order function
     */
    public function applyOrder(&$query)
    {
        foreach($this->orderable as $orderable)
        {
            $column = explode('.', $orderable)[0];
            $direction=  explode('.', $orderable)[1] ?? config('order.default_order_direction');
            if(isset($column)) $query->orderBy($column, $direction);
        }
    }
}
