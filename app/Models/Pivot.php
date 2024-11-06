<?php

namespace App\Models;

use App\Traits\MainModelTrait;
use Illuminate\Database\Eloquent\Relations\Pivot as MainPivot;

class Pivot extends MainPivot
{
    use MainModelTrait;
}
