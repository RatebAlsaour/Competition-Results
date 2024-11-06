<?php

namespace App\Models;

use App\Traits\MainModelTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;

class AuthModel extends Authenticatable
{
    use MainModelTrait;
}
