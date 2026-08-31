<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateAppSettingsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Content\AppSettingsService;
use Illuminate\Http\JsonResponse;

class AdminAppSettingController extends Controller
{
    public function __construct(protected AppSettingsService $appSettingsService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->appSettingsService->getBookingConfig());
    }

    public function update(UpdateAppSettingsRequest $request): JsonResponse
    {
        foreach ($request->settings as $key => $value) {
            $type = is_int($value) ? 'int' : (is_float($value) ? 'float' : (is_bool($value) ? 'bool' : 'string'));
            $this->appSettingsService->set($key, $value, $type);
        }

        return ApiResponse::success($this->appSettingsService->getBookingConfig(), 'Settings updated.');
    }
}