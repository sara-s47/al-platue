<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(protected ReportingService $reportingService) {}

    public function revenue(Request $request): JsonResponse
    {
        [$from, $to, $studioId] = $this->filters($request);

        return ApiResponse::success($this->reportingService->revenue($from, $to, $studioId));
    }

    public function utilization(Request $request): JsonResponse
    {
        [$from, $to, $studioId] = $this->filters($request);

        return ApiResponse::success($this->reportingService->studioUtilization($from, $to, $studioId));
    }

    public function loyalty(Request $request): JsonResponse
    {
        [$from, $to] = $this->filters($request);

        return ApiResponse::success($this->reportingService->loyaltyMetrics($from, $to));
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null, 2: int|null}
     */
    protected function filters(Request $request): array
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'studio_id' => 'nullable|integer|exists:studios,id',
        ]);

        return [
            isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null,
            isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null,
            isset($data['studio_id']) ? (int) $data['studio_id'] : null,
        ];
    }
}
