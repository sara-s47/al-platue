<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PreviewNotificationAudienceRequest;
use App\Http\Requests\Api\V1\Admin\SendNotificationRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->notificationService->paginate((int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($n) => new NotificationResource($n)));
    }

    public function options(): JsonResponse
    {
        return ApiResponse::success($this->notificationService->options());
    }

    public function previewAudience(PreviewNotificationAudienceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $count = $this->notificationService->previewAudience(
            $data['audience'],
            $data['user_ids'] ?? null,
        );

        return ApiResponse::success([
            'count' => $count,
            'audience' => $data['audience'],
        ]);
    }

    public function store(SendNotificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $notification = $this->notificationService->create(
            $data,
            $data['user_ids'] ?? null,
            $request->user()->id,
        );

        return ApiResponse::success(new NotificationResource($notification), 'Notification sent.', 201);
    }
}
