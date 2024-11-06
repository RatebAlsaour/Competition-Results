<?php


namespace App\Http\Services;


class ApiResponseService
{
    /**
     *  Main Success Reponse Functions
     *  @param Array $data
     *  @param ?string $msg
     *  @param int $code
     */

    public static function successResponse( $data = [] , $msg = null, $code = 200 )
    {
        return response()->json([
            'message'   => $msg ?? transResponse('success'),
            'success'   => true,
            'data'      => $data
        ],
        $code);
    }

    /**
     *  Main Error Reponse Functions
     *  @param ?string $msg
     *  @param int $code
     *  @param ?Array $data
     */

    public static function errorResponse( $msg = null, $code = 400 , $data = null)
    {
        return response()->json([
            'message'   => $msg,
            'success'   => false,
            'errors'    => $data ?? [$msg ?? transResponse('wrong')]
        ],
        $code);
    }

    /*****************************************************************************************/

    public static function validateResponse( $errors , $code = 422 )
    {
        return static::errorResponse(
            msg: transResponse('validation_error'),
            code: $code,
            data: $errors->all(),
        );
    }

    public static function successMsgResponse( $msg = null, $code = 200 )
    {
        return static::successResponse(
            msg: $msg,
            code: $code
        );
    }

    public static function deletedResponse( $msg = null, $code = 200 )
    {
        return static::successResponse(
            msg: $msg ?? transResponse('deleted'),
            code: $code
        );
    }

    public static function notFoundResponse( $msg = null , $code = 404 )
    {
        return static::errorResponse(
            $msg ?? transResponse('not_found'),
            code: $code
        );
    }

    public static function unauthorizedResponse( $msg = null , $code = 401 )
    {
        return static::errorResponse(
            $msg ?? transResponse('unauthorized'),
            code: $code
        );
    }

    public static function errorMsgResponse( $msg = null , $code = 400 )
    {
        return static::errorResponse(
            msg: $msg,
            code: $code
        );
    }
}
