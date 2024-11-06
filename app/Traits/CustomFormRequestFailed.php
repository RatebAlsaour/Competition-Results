<?php

namespace App\Traits;

trait CustomFormRequestFailed
{

    /**
     * Handle a failed validation attempt.
     *
     * @return void
     *
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            \App\Http\Services\ApiResponseService::validateResponse($validator->errors())
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     */
    protected function failedAuthorization()
    {
        throw new \App\Exceptions\UnauthorizeMsgException();
    }


}
