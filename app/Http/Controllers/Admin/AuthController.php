<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Services\ApiResponseService;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function login(LoginRequest $request)
    {
        $user = $this->authService->login($request->email, $request->password, $request->boolean('remember'));
        return ApiResponseService::successResponse(UserResource::make($user));
    }

    public function me(Request $request)
    {
        return ApiResponseService::successResponse(UserResource::make($request->user()));
    }

    public function logout()
    {
        $this->authService->logout();
        return ApiResponseService::successMsgResponse();
    }
}
