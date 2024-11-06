<?php

namespace App\Exceptions;

use App\Http\Services\ApiResponseService;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnauthorizeMsgException extends Exception
{
    protected $message;
    protected $code;

    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        $this->message = $message ?: transResponse('unauthorized');
        $this->code = $code ?: 401;

        parent::__construct($this->message, $this->code, $previous);
    }

    public function render($request)
    {
        return ApiResponseService::unauthorizedResponse( msg: $this->message, code: $this->code );
    }

    /**
     * Report or log an exception.
     *
     * @return void
     */
    public function report()
    {
        Log::error('Unauthorize Error: ' . $this->message);
    }
}
