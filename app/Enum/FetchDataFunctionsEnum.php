<?php

namespace App\Enum;

enum FetchDataFunctionsEnum: string
{
    case GET        = 'get';
    case ALL        = 'all';
    case PAGINATE   = 'paginate';

    case FIRST      = 'first';
    case FIND       = 'find';

    public function isMustPassingData(): bool
    {
        return match($this)
        {
            self::FIND  => true,
            default     => false
        };
    }

    public function hasPagination(): bool
    {
        return match($this)
        {
            self::PAGINATE  => true,
            default         => false
        };
    }
}
