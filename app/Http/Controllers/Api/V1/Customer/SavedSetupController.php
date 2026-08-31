<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\StoreSavedSetupRequest;
use App\Http\Requests\Api\V1\Customer\UpdateSavedSetupRequest;
use App\Http\Resources\Api\V1\SavedSetupResource;
use App\Http\Responses\ApiResponse;
use App\Services\Engagement\SavedSetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSetupController extends Controller
{
    public function __construct(protected SavedSetupService $savedSetupService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->savedSetupService->listForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($s) => new SavedSetupResource($s)));
    }

    public function store(StoreSavedSetupRequest $request): JsonResponse
    {
        $setup = $this->savedSetupService->create($request->user()->id, $request->validated());

        return ApiResponse::success(new SavedSetupResource($setup), 'Setup saved.', 201);
    }

    public function update(UpdateSavedSetupRequest $request, int $id): JsonResponse
    {
        $setup = $this->savedSetupService->update($id, $request->user()->id, $request->validated());

        return ApiResponse::success(new SavedSetupResource($setup), 'Setup updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->savedSetupService->delete($id, $request->user()->id);

        return ApiResponse::success(message: 'Setup deleted.');
    }
}