<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SetWeeklyScheduleRequest;
use App\Http\Requests\Api\V1\Admin\StoreScheduleOverrideRequest;
use App\Http\Requests\Api\V1\Admin\StoreStudioBlockRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScheduleController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService) {}

    public function effective(Request $request, int $studioId): JsonResponse
    {
        $request->validate(['date' => 'required|date']);
        return ApiResponse::success($this->scheduleService->getEffectiveSchedule($studioId, $request->date));
    }

    public function setWeekly(SetWeeklyScheduleRequest $request, int $studioId): JsonResponse
    {
        return ApiResponse::success($this->scheduleService->setWeeklySchedule($studioId, $request->days));
    }

    public function createOverride(StoreScheduleOverrideRequest $request, int $studioId): JsonResponse
    {
        return ApiResponse::success($this->scheduleService->createOverride($studioId, $request->validated()), null, 201);
    }

    public function deleteOverride(int $overrideId): JsonResponse
    {
        $this->scheduleService->deleteOverride($overrideId);
        return ApiResponse::success(message: 'Override deleted.');
    }

    public function createBlock(StoreStudioBlockRequest $request, int $studioId): JsonResponse
    {
        $data = array_merge($request->validated(), ['created_by' => $request->user()->id]);
        return ApiResponse::success($this->scheduleService->createBlock($studioId, $data), null, 201);
    }

    public function deleteBlock(int $blockId): JsonResponse
    {
        $this->scheduleService->deleteBlock($blockId);
        return ApiResponse::success(message: 'Block deleted.');
    }
}