<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCampaignRequest;
use App\Http\Resources\Api\V1\CampaignResource;
use App\Http\Responses\ApiResponse;
use App\Repositories\Contracts\LoyaltyCampaignRepositoryInterface;
use App\Services\Loyalty\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCampaignController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected LoyaltyCampaignRepositoryInterface $campaignRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->campaignRepository->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($c) => new CampaignResource($c)));
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $d = $request->validated();
        $campaign = $this->campaignService->create($d, $d['segment_id'] ?? null);
        return ApiResponse::success(new CampaignResource($campaign), null, 201);
    }

    public function execute(int $id): JsonResponse
    {
        return ApiResponse::success($this->campaignService->execute($id), 'Campaign executed.');
    }
}