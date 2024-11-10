<?php

namespace App\Traits;

trait HasArgsParams
{
    /**
     * Get the value of a specific key from the arguments array.
     *
     * @param string $key
     * @param mixed ...$args
     * @return mixed|null
     */
    protected static function getArg(string $key, mixed ...$args): mixed
    {
        $args = $args[0];

        foreach ($args as $arg)
        {
            if (is_array($arg) && isset($arg[$key]))
            {
                return $arg[$key];
            }
        }

        return null;
    }


}
