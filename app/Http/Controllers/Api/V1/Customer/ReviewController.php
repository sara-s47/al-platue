<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateReviewRequest;
use App\Http\Requests\Api\V1\Customer\UpdateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->reviewService->listForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($r) => new ReviewResource($r)));
    }

    public function store(CreateReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $bookingId = (int) $data['booking_id'];
        unset($data['booking_id']);

        if (isset($data['dimensions'])) {
            $data['dimension_scores'] = $data['dimensions'];
            unset($data['dimensions']);
        }

        $review = $this->reviewService->create($bookingId, $request->user()->id, $data);

        return ApiResponse::success(new ReviewResource($review), 'Review submitted.', 201);
    }

    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = $this->reviewService->update($id, $request->user()->id, $request->validated());

        return ApiResponse::success(new ReviewResource($review), 'Review updated.');
    }
}
