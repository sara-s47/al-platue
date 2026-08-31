<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\RegisterDeviceRequest;
use App\Http\Requests\Api\V1\Customer\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthenticationService;
use App\Services\Auth\FirebaseDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected AuthenticationService $authService,
        protected FirebaseDeviceService $deviceService,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user), 'Profile updated.');
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->deviceService->registerDevice($request->user(), $request->validated());

        return ApiResponse::success($device, 'Device registered.', 201);
    }

    public function deactivateDevice(Request $request): JsonResponse
    {
        $request->validate(['device_token' => 'required|string']);
        $this->deviceService->deactivateDevice($request->user(), $request->device_token);

        return ApiResponse::success(message: 'Device deactivated.');
    }
}