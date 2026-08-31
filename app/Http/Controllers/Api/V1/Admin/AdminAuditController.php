<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function __construct(protected AuditService $auditService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->auditService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($log) => new AuditLogResource($log)));
    }
}