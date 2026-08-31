<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notificationService->listForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($n) => new NotificationResource($n)));
    }

    public function markRead(Request $request, int $recipientId): JsonResponse
    {
        $this->notificationService->markRead($request->user()->id, $recipientId);

        return ApiResponse::success(message: 'Notification marked as read.');
    }
}