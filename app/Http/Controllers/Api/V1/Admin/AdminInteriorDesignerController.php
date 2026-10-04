<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreInteriorDesignerRequest;
use App\Http\Requests\Api\V1\Admin\UpdateInteriorDesignerRequest;
use App\Http\Resources\Api\V1\InteriorDesignerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\InteriorDesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInteriorDesignerController extends Controller
{
    public function __construct(protected InteriorDesignerService $designerService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->designerService->paginate((int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($d) => new InteriorDesignerResource($d)));
    }

    public function store(StoreInteriorDesignerRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image']);
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('interior-designers', 'public')
            : null;

        return ApiResponse::success(
            new InteriorDesignerResource($this->designerService->create($data, $imagePath)),
            null,
            201,
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new InteriorDesignerResource($this->designerService->find($id)));
    }

    public function update(UpdateInteriorDesignerRequest $request, int $id): JsonResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);
        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('interior-designers', 'public');
        } elseif ($request->boolean('remove_image')) {
            $imagePath = false;
        }

        return ApiResponse::success(
            new InteriorDesignerResource($this->designerService->update($id, $data, $imagePath)),
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->designerService->delete($id);

        return ApiResponse::success(message: 'Interior designer deleted.');
    }
}
