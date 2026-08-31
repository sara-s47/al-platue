<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\BookAgainRequest;
use App\Http\Requests\Api\V1\Customer\CancelBookingRequest;
use App\Http\Requests\Api\V1\Customer\CreateBookingRequest;
use App\Http\Requests\Api\V1\Customer\ExtendBookingRequest;
use App\Http\Requests\Api\V1\Customer\QuoteRequest;
use App\Http\Requests\Api\V1\Customer\RescheduleBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Http\Responses\ApiResponse;
use App\Services\Booking\BookingService;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with('studio')
            ->latest()
            ->paginate((int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($b) => new BookingResource($b)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = Booking::query()->where('user_id', $request->user()->id)->with('studio')->findOrFail($id);

        return ApiResponse::success(new BookingResource($booking));
    }

    public function quote(QuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $quote = $this->bookingService->createQuote(
            $request->user()->id,
            (int) $data['studio_id'],
            Carbon::parse($data['start_at']),
            Carbon::parse($data['end_at']),
            (int) $data['guest_count'],
            $data['equipment'] ?? [],
            $data['hospitality'] ?? [],
            $data['package_id'] ?? null,
            $data['promo_code'] ?? null,
            $data['points_to_redeem'] ?? null,
        );

        return ApiResponse::success(new QuoteResource($quote));
    }

    public function store(CreateBookingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $booking = $this->bookingService->createFromHold(
            (int) $data['hold_id'],
            $request->user()->id,
            (int) $data['guest_count'],
            $data['equipment'] ?? [],
            $data['hospitality'] ?? [],
            $data['package_id'] ?? null,
            $data['promo_code'] ?? null,
            $data['points_to_redeem'] ?? null,
            $data['customer_notes'] ?? null,
        );

        return ApiResponse::success(new BookingResource($booking->load('studio')), 'Booking created.', 201);
    }

    public function cancel(CancelBookingRequest $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->cancel($id, $request->user()->id, $request->reason);

        return ApiResponse::success(new BookingResource($booking), 'Booking cancelled.');
    }

    public function reschedule(RescheduleBookingRequest $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->reschedule(
            $id,
            Carbon::parse($request->start_at),
            Carbon::parse($request->end_at),
            $request->user()->id,
            $request->reason,
        );

        return ApiResponse::success(new BookingResource($booking), 'Booking rescheduled.');
    }

    public function extend(ExtendBookingRequest $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->extend($id, Carbon::parse($request->end_at), $request->user()->id);

        return ApiResponse::success(new BookingResource($booking), 'Booking extended.');
    }

    public function bookAgain(BookAgainRequest $request, int $id): JsonResponse
    {
        $booking = $this->bookingService->bookAgain($id, Carbon::parse($request->start_at), $request->user()->id);

        return ApiResponse::success(new BookingResource($booking->load('studio')), 'Booking recreated.', 201);
    }
}