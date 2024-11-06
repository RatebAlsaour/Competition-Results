<?php

namespace App\Enum;

enum LoadableRelationsEnum: string
{
    //

    public function relationName(): ?string
    {
        return match($this) {
            //
            default => null
        };
    }
}
