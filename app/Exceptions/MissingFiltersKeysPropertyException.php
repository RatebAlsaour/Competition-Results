<?php

namespace App\Exceptions;

use Exception;

class MissingFiltersKeysPropertyException extends Exception
{
    public function __construct($repository)
    {
        $message = "Repository '" . get_class($repository) . "' doesn't have filtersKeys attributes.";
        parent::__construct($message);
    }
}
