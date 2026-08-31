<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Reporting\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(protected ReportingService $reportingService) {}

    public function revenue(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportingService->revenue($request->only(['from', 'to'])));
    }

    public function utilization(Request $request): JsonResponse
    {
        return ApiResponse::success($this->reportingService->utilization($request->only(['studio_id'])));
    }

    public function loyalty(): JsonResponse
    {
        return ApiResponse::success($this->reportingService->loyalty());
    }
}