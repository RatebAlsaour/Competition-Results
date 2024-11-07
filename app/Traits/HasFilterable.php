<?php

namespace App\Traits;

trait HasFilterable
{
    use FilteringFunctions;

    /**
     * Array with filterable keys
     * You can pass filter key name then "=>" filter class namespace (ex: "status" => StatusFilter::class)
     */
    public $filtersKeys = [];
}
