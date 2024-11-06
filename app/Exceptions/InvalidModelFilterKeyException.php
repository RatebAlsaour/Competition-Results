<?php

namespace App\Exceptions;

use Exception;

class InvalidModelFilterKeyException extends Exception
{
    public function __construct($key, $modelName)
    {
        $message = "Invalid key '$key' in '$modelName'.";
        parent::__construct($message);
    }
}
