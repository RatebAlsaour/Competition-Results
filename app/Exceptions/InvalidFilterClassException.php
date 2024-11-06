<?php

namespace App\Exceptions;

use Exception;

class InvalidFilterClassException extends Exception
{
    public function __construct($filterClass)
    {
        $message = "Trying to declare invalid filter class: '$filterClass'.";
        parent::__construct($message);
    }
}
