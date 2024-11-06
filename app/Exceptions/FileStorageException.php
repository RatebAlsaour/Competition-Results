<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Http\Services\ApiResponseService;

class FileStorageException extends Exception
{
    protected $message;
    protected $code;

    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        $this->message = $message ?: trans('file.store');
        $this->code = $code ?: 400;

        parent::__construct($this->message, $this->code, $previous);
    }

    public function render($request)
    {
        return ApiResponseService::errorMsgResponse($this->message, $this->code);
    }

    public function report()
    {
        Log::error('File Storage Exception: ' . $this->message);
    }
}
