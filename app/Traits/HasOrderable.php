<?php

namespace App\Traits;

trait HasOrderable
{
    use OrderingFunctions;

    /**
     * Array containing fields that can be order by them
     * You can pass order direction after the field name
     */
    public $orderable = ['created_at.asc'];
}
