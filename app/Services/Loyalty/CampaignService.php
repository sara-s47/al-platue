<?php

namespace App\Services\Loyalty;

use App\Enums\CampaignStatus;
use App\Enums\CampaignUserStatus;
use App\Enums\PointsTransactionType;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\LoyaltyCampaignRepositoryInterface;
use App\Repositories\Contracts\PointsTransactionRepositoryInterface;
use App\Services\Promotion\SegmentationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    public function __construct(
        protected LoyaltyCampaignRepositoryInterface $campaignRepository,
        protected PointsTransactionRepositoryInterface $pointsTransactionRepository,
        protected LoyaltyService $loyaltyService,
        protected SegmentationService $segmentationService,
    ) {
    }

    public function create(array $data, ?int $segmentId = null): Model
    {
        return DB::transaction(function () use ($data, $segmentId) {
            $campaign = $this->campaignRepository->create(array_merge($data, [
                'status' => CampaignStatus::Draft->value,
            ]));

            if ($segmentId) {
                $userIds = $this->segmentationService->getUsersForSegment($segmentId);

                foreach ($userIds as $userId) {
                    DB::table('loyalty_campaign_users')->insert([
                        'campaign_id' => $campaign->id,
                        'user_id' => $userId,
                        'status' => CampaignUserStatus::Pending->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $campaign->fresh();
        });
    }

    public function execute(int $campaignId): array
    {
        return DB::transaction(function () use ($campaignId) {
            $campaign = $this->campaignRepository->findOrFail($campaignId);

            if (! in_array($campaign->status, [
                CampaignStatus::Draft,
                CampaignStatus::Scheduled,
            ], true)) {
                throw new BusinessException('Campaign cannot be executed in its current state.', 'campaign_not_executable');
            }

            $this->campaignRepository->update($campaignId, [
                'status' => CampaignStatus::Running->value,
            ]);

            $users = DB::table('loyalty_campaign_users')
                ->where('campaign_id', $campaignId)
                ->where('status', CampaignUserStatus::Pending->value)
                ->get();

            $awarded = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($users as $campaignUser) {
                try {
                    $alreadyAwarded = DB::table('points_transactions')
                        ->where('user_id', $campaignUser->user_id)
                        ->where('campaign_id', $campaignId)
                        ->where('type', PointsTransactionType::CampaignAward->value)
                        ->exists();

                    if ($alreadyAwarded) {
                        DB::table('loyalty_campaign_users')
                            ->where('id', $campaignUser->id)
                            ->update(['status' => CampaignUserStatus::Awarded->value, 'updated_at' => now()]);
                        $skipped++;

                        continue;
                    }

                    $this->pointsTransactionRepository->create([
                        'user_id' => $campaignUser->user_id,
                        'campaign_id' => $campaignId,
                        'type' => PointsTransactionType::CampaignAward->value,
                        'points' => (float) $campaign->points,
                        'reason' => $campaign->reason ?? $campaign->name,
                        'created_at' => now(),
                    ]);

                    DB::table('loyalty_campaign_users')
                        ->where('id', $campaignUser->id)
                        ->update(['status' => CampaignUserStatus::Awarded->value, 'updated_at' => now()]);

                    $awarded++;
                } catch (\Throwable) {
                    DB::table('loyalty_campaign_users')
                        ->where('id', $campaignUser->id)
                        ->update(['status' => CampaignUserStatus::Failed->value, 'updated_at' => now()]);
                    $failed++;
                }
            }

            $this->campaignRepository->update($campaignId, [
                'status' => CampaignStatus::Completed->value,
            ]);

            return compact('awarded', 'skipped', 'failed');
        });
    }
}
