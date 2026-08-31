<?php

namespace App\Services\Review;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(
        protected ReviewRepositoryInterface $reviewRepository,
        protected BookingRepositoryInterface $bookingRepository,
    ) {
    }

    public function create(int $bookingId, int $userId, array $data): Model
    {
        return DB::transaction(function () use ($bookingId, $userId, $data) {
            $booking = $this->bookingRepository->findOrFail($bookingId);

            if ((int) $booking->user_id !== $userId) {
                throw new BusinessException('You can only review your own bookings.', 'review_unauthorized', 403);
            }

            if ($booking->status !== BookingStatus::Completed->value) {
                throw new BusinessException('Only completed bookings can be reviewed.', 'booking_not_reviewable');
            }

            if ($this->reviewRepository->findByBookingId($bookingId)) {
                throw new BusinessException('A review already exists for this booking.', 'review_exists');
            }

            $review = $this->reviewRepository->create([
                'booking_id' => $bookingId,
                'user_id' => $userId,
                'studio_id' => (int) $booking->studio_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => ReviewStatus::Pending->value,
            ]);

            if ($dimensionScores = $data['dimension_scores'] ?? null) {
                $this->syncDimensionScores($review->id, $dimensionScores);
            }

            return $review->fresh(['dimensionScores']);
        });
    }

    public function update(int $reviewId, int $userId, array $data): Model
    {
        return DB::transaction(function () use ($reviewId, $userId, $data) {
            $review = $this->reviewRepository->findOrFail($reviewId);

            if ((int) $review->user_id !== $userId) {
                throw new BusinessException('You can only update your own reviews.', 'review_unauthorized', 403);
            }

            if ($review->status === ReviewStatus::Rejected) {
                throw new BusinessException('Rejected reviews cannot be updated.', 'review_not_editable');
            }

            $payload = ['edited_at' => now()];

            if (array_key_exists('rating', $data)) {
                $payload['rating'] = $data['rating'];
            }

            if (array_key_exists('comment', $data)) {
                $payload['comment'] = $data['comment'];
            }

            $updated = $this->reviewRepository->update($reviewId, $payload);

            if ($dimensionScores = $data['dimension_scores'] ?? null) {
                $this->syncDimensionScores($reviewId, $dimensionScores);
            }

            return $updated->fresh(['dimensionScores']);
        });
    }

    public function moderate(int $reviewId, ReviewStatus $status): Model
    {
        if (! in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected, ReviewStatus::Hidden], true)) {
            throw new BusinessException('Invalid moderation status.', 'invalid_review_status');
        }

        return $this->reviewRepository->update($reviewId, [
            'status' => $status->value,
        ]);
    }

    public function listForStudio(int $studioId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->reviewRepository->listForStudio($studioId, $filters, $perPage);
    }

    /**
     * @param  array<int, array{dimension_id: int, score: int}>  $dimensionScores
     */
    protected function syncDimensionScores(int $reviewId, array $dimensionScores): void
    {
        DB::table('review_dimension_scores')->where('review_id', $reviewId)->delete();

        $rows = [];

        foreach ($dimensionScores as $score) {
            $rows[] = [
                'review_id' => $reviewId,
                'dimension_id' => $score['dimension_id'],
                'score' => $score['score'],
            ];
        }

        if ($rows !== []) {
            DB::table('review_dimension_scores')->insert($rows);
        }
    }
}
