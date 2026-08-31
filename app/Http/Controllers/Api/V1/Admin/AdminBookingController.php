<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AddBookingNoteRequest;
use App\Http\Requests\Api\V1\Admin\UpdateBookingStatusRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}

    public function index(Request $request): JsonResponse
    {
        $p = Booking::query()->with('studio')->latest()->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($b) => new BookingResource($b)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new BookingResource(Booking::with('studio')->findOrFail($id)));
    }

    public function updateStatus(UpdateBookingStatusRequest $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->updateStatus($id, BookingStatus::from($request->status));
        return ApiResponse::success(new BookingResource($booking));
    }

    public function addNote(AddBookingNoteRequest $request, int $id): JsonResponse
    {
        $this->bookingService->addInternalNote($id, $request->user()->id, $request->note);
        return ApiResponse::success(message: 'Note added.');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->cancel($id, $request->user()->id, $request->input('reason'));
        return ApiResponse::success(new BookingResource($booking));
    }
}