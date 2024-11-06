<?php

namespace App\Exceptions;

use Exception;

class InvalidFilterKeyException extends Exception
{
    public function __construct($filterKey)
    {
        $message = "Invalid filter key: '$filterKey'.";
        parent::__construct($message);
    }
}
