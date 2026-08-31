<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginOtpRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthenticationService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return ApiResponse::success(new UserResource($user), 'Registration successful. Please verify OTP.', 201);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->authService->verifyOtpAndActivate($request->phone, $request->code);

        return ApiResponse::success(new AuthTokenResource($result), 'Account activated.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->phone, $request->password);

        return ApiResponse::success(new AuthTokenResource($result));
    }

    public function loginWithOtp(LoginOtpRequest $request): JsonResponse
    {
        $result = $this->authService->loginWithOtp($request->phone, $request->code);

        return ApiResponse::success(new AuthTokenResource($result));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->forgotPassword($request->phone);

        return ApiResponse::success(message: 'OTP sent for password reset.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->phone, $request->code, $request->password);

        return ApiResponse::success(message: 'Password reset successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::success(message: 'Logged out.');
    }
}