<?php

namespace App\Exceptions;

use App\Http\Services\ApiResponseService;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorMsgException extends Exception
{
    protected $message;
    protected $code;

    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        $this->message = $message ?: transResponse('wrong');
        $this->code = $code ?: 400;

        parent::__construct($this->message, $this->code, $previous);
    }

    public function render($request)
    {
        return ApiResponseService::errorMsgResponse($this->message, $this->code);
    }

    /**
     * Report or log an exception.
     *
     * @return void
     */
    public function report()
    {
        Log::error('Error: ' . $this->message);
    }
}
