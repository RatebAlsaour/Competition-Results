<?php

namespace App\Traits;

use App\Observers\ModelObserver;

trait HasModelObserver
{
    public static function bootHasModelObserver()
    {
        static::observe(new ModelObserver);
    }
}
