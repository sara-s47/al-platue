<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SetWeeklyScheduleRequest;
use App\Http\Requests\Api\V1\Admin\StoreScheduleOverrideRequest;
use App\Http\Requests\Api\V1\Admin\StoreStudioBlockRequest;
use App\Http\Requests\Api\V1\Admin\UpdateScheduleOverrideRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStudioBlockRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminScheduleController extends Controller
{
    public function __construct(protected ScheduleService $scheduleService) {}

    public function weekly(int $studioId): JsonResponse
    {
        return ApiResponse::success($this->scheduleService->getWeeklySchedule($studioId));
    }

    public function overview(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return ApiResponse::success(
            $this->scheduleService->getOverview($studioId, $request->get('from'), $request->get('to'))
        );
    }

    public function day(Request $request, int $studioId): JsonResponse
    {
        $request->validate(['date' => 'required|date']);

        return ApiResponse::success($this->scheduleService->getDayDetails($studioId, $request->date));
    }

    public function effective(Request $request, int $studioId): JsonResponse
    {
        $request->validate(['date' => 'required|date']);

        return ApiResponse::success($this->scheduleService->getEffectiveSchedule($studioId, $request->date));
    }

    public function setWeekly(SetWeeklyScheduleRequest $request, int $studioId): JsonResponse
    {
        return ApiResponse::success($this->scheduleService->setWeeklySchedule($studioId, $request->days));
    }

    public function listOverrides(Request $request, int $studioId): JsonResponse
    {
        $paginator = $this->scheduleService->paginateOverrides(
            $studioId,
            (int) $request->get('per_page', 15),
        );

        return ApiResponse::paginated($paginator);
    }

    public function createOverride(StoreScheduleOverrideRequest $request, int $studioId): JsonResponse
    {
        return ApiResponse::success(
            $this->scheduleService->createOverride($studioId, $request->validated()),
            null,
            201,
        );
    }

    public function updateOverride(UpdateScheduleOverrideRequest $request, int $studioId, int $overrideId): JsonResponse
    {
        return ApiResponse::success(
            $this->scheduleService->updateOverride($studioId, $overrideId, $request->validated())
        );
    }

    public function deleteOverride(int $studioId, int $overrideId): JsonResponse
    {
        $this->scheduleService->deleteOverride($overrideId);

        return ApiResponse::success(message: 'Override deleted.');
    }

    public function listBlocks(Request $request, int $studioId): JsonResponse
    {
        $paginator = $this->scheduleService->paginateBlocks(
            $studioId,
            (int) $request->get('per_page', 15),
        );

        return ApiResponse::paginated($paginator);
    }

    public function createBlock(StoreStudioBlockRequest $request, int $studioId): JsonResponse
    {
        $data = array_merge($request->validated(), ['created_by' => $request->user()->id]);

        return ApiResponse::success($this->scheduleService->createBlock($studioId, $data), null, 201);
    }

    public function updateBlock(UpdateStudioBlockRequest $request, int $studioId, int $blockId): JsonResponse
    {
        return ApiResponse::success(
            $this->scheduleService->updateBlock($studioId, $blockId, $request->validated())
        );
    }

    public function deleteBlock(int $studioId, int $blockId): JsonResponse
    {
        $this->scheduleService->deleteBlock($blockId);

        return ApiResponse::success(message: 'Block deleted.');
    }
}
