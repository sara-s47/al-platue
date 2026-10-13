<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminNotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\Notification\AdminNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInboxController extends Controller
{
    public function __construct(protected AdminNotificationService $adminNotificationService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'unread' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->adminNotificationService->listForAdmin(
            $request->user()->id,
            $request->boolean('unread'),
            (int) $request->get('per_page', 15),
        );

        return ApiResponse::paginated($paginator->through(fn ($n) => new AdminNotificationResource($n)));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'unread_count' => $this->adminNotificationService->unreadCount($request->user()->id),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->adminNotificationService->markRead($id, $request->user()->id);

        return ApiResponse::success(new AdminNotificationResource($notification), 'Notification marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->adminNotificationService->markAllRead($request->user()->id);

        return ApiResponse::success(['updated' => $updated], 'All notifications marked as read.');
    }
}
