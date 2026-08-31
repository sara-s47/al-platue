<?php

namespace App\Providers;

use App\Repositories\Contracts\AppSettingRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\ContentBannerRepositoryInterface;
use App\Repositories\Contracts\ContentPageRepositoryInterface;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\FavoriteRepositoryInterface;
use App\Repositories\Contracts\FirebaseDeviceRepositoryInterface;
use App\Repositories\Contracts\HospitalityCategoryRepositoryInterface;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;
use App\Repositories\Contracts\LoyaltyCampaignRepositoryInterface;
use App\Repositories\Contracts\LoyaltyRuleRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PointsTransactionRepositoryInterface;
use App\Repositories\Contracts\PricingRuleRepositoryInterface;
use App\Repositories\Contracts\PromoCodeRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\ReschedulePolicyRepositoryInterface;
use App\Repositories\Contracts\ReviewDimensionRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\SavedSetupRepositoryInterface;
use App\Repositories\Contracts\SegmentRepositoryInterface;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleOverrideRepositoryInterface;
use App\Repositories\Contracts\StudioScheduleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\EloquentAppSettingRepository;
use App\Repositories\Eloquent\EloquentAuditLogRepository;
use App\Repositories\Eloquent\EloquentBookingHoldRepository;
use App\Repositories\Eloquent\EloquentBookingRepository;
use App\Repositories\Eloquent\EloquentCancellationPolicyRepository;
use App\Repositories\Eloquent\EloquentCategoryRepository;
use App\Repositories\Eloquent\EloquentContentBannerRepository;
use App\Repositories\Eloquent\EloquentContentPageRepository;
use App\Repositories\Eloquent\EloquentEquipmentRepository;
use App\Repositories\Eloquent\EloquentFavoriteRepository;
use App\Repositories\Eloquent\EloquentFirebaseDeviceRepository;
use App\Repositories\Eloquent\EloquentHospitalityCategoryRepository;
use App\Repositories\Eloquent\EloquentHospitalityItemRepository;
use App\Repositories\Eloquent\EloquentLoyaltyCampaignRepository;
use App\Repositories\Eloquent\EloquentLoyaltyRuleRepository;
use App\Repositories\Eloquent\EloquentNotificationRepository;
use App\Repositories\Eloquent\EloquentPackageRepository;
use App\Repositories\Eloquent\EloquentPaymentRepository;
use App\Repositories\Eloquent\EloquentPointsTransactionRepository;
use App\Repositories\Eloquent\EloquentPricingRuleRepository;
use App\Repositories\Eloquent\EloquentPromoCodeRepository;
use App\Repositories\Eloquent\EloquentRefundRepository;
use App\Repositories\Eloquent\EloquentReschedulePolicyRepository;
use App\Repositories\Eloquent\EloquentReviewDimensionRepository;
use App\Repositories\Eloquent\EloquentReviewRepository;
use App\Repositories\Eloquent\EloquentSavedSetupRepository;
use App\Repositories\Eloquent\EloquentSegmentRepository;
use App\Repositories\Eloquent\EloquentStudioBlockRepository;
use App\Repositories\Eloquent\EloquentStudioRepository;
use App\Repositories\Eloquent\EloquentStudioScheduleOverrideRepository;
use App\Repositories\Eloquent\EloquentStudioScheduleRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Services\Auth\LogOtpDriver;
use App\Services\Auth\OtpServiceInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OtpServiceInterface::class, LogOtpDriver::class);

        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(FirebaseDeviceRepositoryInterface::class, EloquentFirebaseDeviceRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(StudioRepositoryInterface::class, EloquentStudioRepository::class);
        $this->app->bind(StudioScheduleRepositoryInterface::class, EloquentStudioScheduleRepository::class);
        $this->app->bind(StudioScheduleOverrideRepositoryInterface::class, EloquentStudioScheduleOverrideRepository::class);
        $this->app->bind(StudioBlockRepositoryInterface::class, EloquentStudioBlockRepository::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EloquentEquipmentRepository::class);
        $this->app->bind(HospitalityCategoryRepositoryInterface::class, EloquentHospitalityCategoryRepository::class);
        $this->app->bind(HospitalityItemRepositoryInterface::class, EloquentHospitalityItemRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, EloquentPackageRepository::class);
        $this->app->bind(PricingRuleRepositoryInterface::class, EloquentPricingRuleRepository::class);
        $this->app->bind(BookingHoldRepositoryInterface::class, EloquentBookingHoldRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, EloquentBookingRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(RefundRepositoryInterface::class, EloquentRefundRepository::class);
        $this->app->bind(CancellationPolicyRepositoryInterface::class, EloquentCancellationPolicyRepository::class);
        $this->app->bind(ReschedulePolicyRepositoryInterface::class, EloquentReschedulePolicyRepository::class);
        $this->app->bind(LoyaltyRuleRepositoryInterface::class, EloquentLoyaltyRuleRepository::class);
        $this->app->bind(PointsTransactionRepositoryInterface::class, EloquentPointsTransactionRepository::class);
        $this->app->bind(LoyaltyCampaignRepositoryInterface::class, EloquentLoyaltyCampaignRepository::class);
        $this->app->bind(SegmentRepositoryInterface::class, EloquentSegmentRepository::class);
        $this->app->bind(PromoCodeRepositoryInterface::class, EloquentPromoCodeRepository::class);
        $this->app->bind(FavoriteRepositoryInterface::class, EloquentFavoriteRepository::class);
        $this->app->bind(SavedSetupRepositoryInterface::class, EloquentSavedSetupRepository::class);
        $this->app->bind(ReviewRepositoryInterface::class, EloquentReviewRepository::class);
        $this->app->bind(ReviewDimensionRepositoryInterface::class, EloquentReviewDimensionRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(ContentBannerRepositoryInterface::class, EloquentContentBannerRepository::class);
        $this->app->bind(ContentPageRepositoryInterface::class, EloquentContentPageRepository::class);
        $this->app->bind(AppSettingRepositoryInterface::class, EloquentAppSettingRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, EloquentAuditLogRepository::class);
    }
}
