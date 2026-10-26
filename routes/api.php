<?php

use App\Http\Controllers\Api\V1\Admin\AdminAccessoryCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminAccessoryController;
use App\Http\Controllers\Api\V1\Admin\AdminAccessoryRentalController;
use App\Http\Controllers\Api\V1\Admin\AdminAppSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditController;
use App\Http\Controllers\Api\V1\Admin\AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\AdminCampaignController;
use App\Http\Controllers\Api\V1\Admin\AdminCancellationPolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminCatalogSectionCoverController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminContentController;
use App\Http\Controllers\Api\V1\Admin\AdminEquipmentController;
use App\Http\Controllers\Api\V1\Admin\AdminEquipmentRentalController;
use App\Http\Controllers\Api\V1\Admin\AdminHospitalityController;
use App\Http\Controllers\Api\V1\Admin\AdminCaravanController;
use App\Http\Controllers\Api\V1\Admin\AdminGeneratorController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminPortableBathroomController;
use App\Http\Controllers\Api\V1\Admin\AdminRentalOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminStudioPaintColorController;
use App\Http\Controllers\Api\V1\Admin\AdminInboxController;
use App\Http\Controllers\Api\V1\Admin\AdminInteriorDesignerController;
use App\Http\Controllers\Api\V1\Admin\AdminLoyaltyController;
use App\Http\Controllers\Api\V1\Admin\AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\AdminPackageController;
use App\Http\Controllers\Api\V1\Admin\AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\AdminPricingRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminPromoCodeController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminReschedulePolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminScheduleController;
use App\Http\Controllers\Api\V1\Admin\AdminSegmentController;
use App\Http\Controllers\Api\V1\Admin\AdminStaffController;
use App\Http\Controllers\Api\V1\Admin\AdminStudioController;
use App\Http\Controllers\Api\V1\Admin\AdminStudioItemController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Customer\AccessoryCategoryController;
use App\Http\Controllers\Api\V1\Customer\AccessoryController;
use App\Http\Controllers\Api\V1\Customer\AccessoryRentalController;
use App\Http\Controllers\Api\V1\Customer\AvailabilityController;
use App\Http\Controllers\Api\V1\Customer\BookingController;
use App\Http\Controllers\Api\V1\Customer\BookingHoldController;
use App\Http\Controllers\Api\V1\Customer\CaravanController;
use App\Http\Controllers\Api\V1\Customer\CartController;
use App\Http\Controllers\Api\V1\Customer\CatalogSectionCoverController;
use App\Http\Controllers\Api\V1\Customer\CategoryController;
use App\Http\Controllers\Api\V1\Customer\GeneratorController;
use App\Http\Controllers\Api\V1\Customer\OrderController;
use App\Http\Controllers\Api\V1\Customer\PortableBathroomController;
use App\Http\Controllers\Api\V1\Customer\ContentController;
use App\Http\Controllers\Api\V1\Customer\EquipmentController;
use App\Http\Controllers\Api\V1\Customer\EquipmentRentalController;
use App\Http\Controllers\Api\V1\Customer\FavoriteController;
use App\Http\Controllers\Api\V1\Customer\HospitalityController;
use App\Http\Controllers\Api\V1\Customer\InteriorDesignerController;
use App\Http\Controllers\Api\V1\Customer\LoyaltyController;
use App\Http\Controllers\Api\V1\Customer\NotificationController;
use App\Http\Controllers\Api\V1\Customer\PackageController;
use App\Http\Controllers\Api\V1\Customer\PaymentController;
use App\Http\Controllers\Api\V1\Customer\ProfileController;
use App\Http\Controllers\Api\V1\Customer\PromoCodeController;
use App\Http\Controllers\Api\V1\Customer\RentalOrderController;
use App\Http\Controllers\Api\V1\Customer\ReviewController;
use App\Http\Controllers\Api\V1\Customer\SavedSetupController;
use App\Http\Controllers\Api\V1\Customer\StudioItemController as CustomerStudioItemController;
use App\Http\Controllers\Api\V1\Customer\StudioController;
// use App\Http\Controllers\Api\V1\Webhooks\PaymobWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('login-otp', [AuthController::class, 'loginWithOtp']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    Route::prefix('customer')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('devices', [ProfileController::class, 'registerDevice']);
        Route::delete('devices', [ProfileController::class, 'deactivateDevice']);

        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('studios', [StudioController::class, 'index']);
        Route::get('studios/ordered', [StudioController::class, 'ordered']);
        Route::get('studios/{id}', [StudioController::class, 'show'])->whereNumber('id');
        Route::get('studios/{studioId}/availability', [AvailabilityController::class, 'index']);
        Route::get('studios/{studioId}/availability/daily', [AvailabilityController::class, 'daily']);
        Route::get('studios/{studioId}/equipment', [EquipmentController::class, 'index']);
        Route::get('studios/{studioId}/hospitality', [HospitalityController::class, 'index']);
        Route::get('studios/{studioId}/items', [CustomerStudioItemController::class, 'index']);
        Route::get('studios/{studioId}/packages', [PackageController::class, 'index']);

        Route::post('booking-holds', [BookingHoldController::class, 'store']);
        Route::delete('booking-holds/{id}', [BookingHoldController::class, 'destroy']);

        Route::post('bookings/quote', [BookingController::class, 'quote']);
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::get('bookings/{id}', [BookingController::class, 'show']);
        Route::post('bookings/{id}/cancel', [BookingController::class, 'cancel']);
        Route::post('bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
        Route::post('bookings/{id}/extend', [BookingController::class, 'extend']);
        Route::post('bookings/{id}/book-again', [BookingController::class, 'bookAgain']);

        Route::post('packages/quote', [PackageController::class, 'quote']);
        Route::post('packages/hold', [PackageController::class, 'hold']);
        Route::get('packages/{id}', [PackageController::class, 'show']);
        // Paymob is temporarily disabled; bookings are cash on delivery (admin records cash via admin/bookings/{id}/mark-paid).
        // Route::post('bookings/{bookingId}/payments', [PaymentController::class, 'initiate']);
        Route::get('payments/{id}', [PaymentController::class, 'show']);

        Route::get('accessory-categories', [AccessoryCategoryController::class, 'index']);
        Route::get('accessories', [AccessoryController::class, 'index']);
        Route::get('accessories/availability', [AccessoryController::class, 'availability']);
        Route::get('accessories/{id}', [AccessoryController::class, 'show'])->whereNumber('id');

        Route::get('portable-bathrooms', [PortableBathroomController::class, 'index']);
        Route::get('portable-bathrooms/availability', [PortableBathroomController::class, 'availability']);
        Route::get('portable-bathrooms/{id}', [PortableBathroomController::class, 'show'])->whereNumber('id');

        Route::get('caravans', [CaravanController::class, 'index']);
        Route::get('caravans/availability', [CaravanController::class, 'availability']);
        Route::get('caravans/{id}', [CaravanController::class, 'show'])->whereNumber('id');

        Route::get('generators', [GeneratorController::class, 'index']);
        Route::get('generators/availability', [GeneratorController::class, 'availability']);
        Route::get('generators/{id}', [GeneratorController::class, 'show'])->whereNumber('id');

        Route::post('accessory-rentals/quote', [AccessoryRentalController::class, 'quote']);
        Route::get('accessory-rentals', [AccessoryRentalController::class, 'index']);
        Route::post('accessory-rentals', [AccessoryRentalController::class, 'store']);
        Route::get('accessory-rentals/{id}', [AccessoryRentalController::class, 'show'])->whereNumber('id');
        Route::post('accessory-rentals/{id}/cancel', [AccessoryRentalController::class, 'cancel']);

        Route::get('equipment', [EquipmentController::class, 'catalog']);
        Route::get('equipment/availability', [EquipmentController::class, 'availability']);
        Route::get('equipment/{id}', [EquipmentController::class, 'show'])->whereNumber('id');

        Route::post('equipment-rentals/quote', [EquipmentRentalController::class, 'quote']);
        Route::get('equipment-rentals', [EquipmentRentalController::class, 'index']);
        Route::post('equipment-rentals', [EquipmentRentalController::class, 'store']);
        Route::get('equipment-rentals/{id}', [EquipmentRentalController::class, 'show'])->whereNumber('id');
        Route::post('equipment-rentals/{id}/cancel', [EquipmentRentalController::class, 'cancel']);

        Route::post('orders/quote', [OrderController::class, 'quote']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);

        // Legacy aliases
        Route::post('rental-orders/quote', [RentalOrderController::class, 'quote']);
        Route::get('rental-orders', [RentalOrderController::class, 'index']);
        Route::get('rental-orders/{id}', [RentalOrderController::class, 'show'])->whereNumber('id');
        Route::post('rental-orders/{id}/cancel', [RentalOrderController::class, 'cancel']);

        Route::get('interior-designers', [InteriorDesignerController::class, 'index']);
        Route::get('interior-designers/{id}', [InteriorDesignerController::class, 'show'])->whereNumber('id');

        Route::get('loyalty/balance', [LoyaltyController::class, 'balance']);
        Route::get('loyalty/history', [LoyaltyController::class, 'history']);
        Route::post('promo-codes/validate', [PromoCodeController::class, 'validate']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites', [FavoriteController::class, 'store']);
        Route::delete('favorites/{type}/{id}', [FavoriteController::class, 'destroy'])
            ->whereIn('type', ['studio', 'equipment', 'accessory']);
        Route::delete('favorites/{studioId}', [FavoriteController::class, 'destroyStudio'])
            ->whereNumber('studioId');

        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart', [CartController::class, 'store']);
        Route::post('cart/checkout', [CartController::class, 'checkout']);
        Route::delete('cart', [CartController::class, 'clear']);
        Route::put('cart/{type}/{id}', [CartController::class, 'update'])
            ->whereIn('type', ['equipment', 'accessory', 'studio', 'portable_bathroom', 'caravan', 'generator']);
        Route::delete('cart/{type}/{id}', [CartController::class, 'destroy'])
            ->whereIn('type', ['equipment', 'accessory', 'studio', 'portable_bathroom', 'caravan', 'generator']);

        Route::apiResource('saved-setups', SavedSetupController::class)->except(['show']);

        Route::get('reviews', [ReviewController::class, 'index']);
        Route::post('reviews', [ReviewController::class, 'store']);
        Route::put('reviews/{id}', [ReviewController::class, 'update']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{recipientId}/read', [NotificationController::class, 'markRead']);

        Route::get('content/banners', [ContentController::class, 'banners']);
        Route::get('content/pages/{slug}', [ContentController::class, 'page']);
        Route::get('content/settings', [ContentController::class, 'settings']);
        Route::get('catalog-section-covers', [CatalogSectionCoverController::class, 'index']);
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'active'])->group(function () {
        Route::middleware('role:super_admin|admin|staff')->prefix('inbox')->group(function () {
            Route::get('/', [AdminInboxController::class, 'index']);
            Route::get('unread-count', [AdminInboxController::class, 'unreadCount']);
            Route::post('read-all', [AdminInboxController::class, 'markAllRead']);
            Route::post('{id}/read', [AdminInboxController::class, 'markRead'])->whereNumber('id');
        });

        Route::middleware('permission:categories.manage')->prefix('categories')->group(function () {
            Route::get('/', [AdminCategoryController::class, 'index']);
            Route::post('/', [AdminCategoryController::class, 'store']);
            Route::get('{id}', [AdminCategoryController::class, 'show']);
            Route::put('{id}', [AdminCategoryController::class, 'update']);
            Route::delete('{id}', [AdminCategoryController::class, 'destroy']);
            Route::post('reorder', [AdminCategoryController::class, 'reorder']);
            Route::post('{id}/toggle', [AdminCategoryController::class, 'toggle']);
        });

        Route::middleware('permission:studios.manage')->prefix('studios')->group(function () {
            Route::get('/', [AdminStudioController::class, 'index']);
            Route::post('/', [AdminStudioController::class, 'store']);
            Route::post('reorder', [AdminStudioController::class, 'reorder']);
            Route::get('{id}', [AdminStudioController::class, 'show']);
            Route::put('{id}', [AdminStudioController::class, 'update']);
            Route::delete('{id}', [AdminStudioController::class, 'destroy']);
            Route::post('{id}/toggle', [AdminStudioController::class, 'toggle']);
            Route::post('{id}/images', [AdminStudioController::class, 'uploadImages']);
            Route::get('{studioId}/items', [AdminStudioItemController::class, 'index']);
            Route::post('{studioId}/items', [AdminStudioItemController::class, 'store']);
            Route::get('{studioId}/items/{id}', [AdminStudioItemController::class, 'show']);
            Route::put('{studioId}/items/{id}', [AdminStudioItemController::class, 'update']);
            Route::post('{studioId}/items/{id}', [AdminStudioItemController::class, 'update']);
            Route::delete('{studioId}/items/{id}', [AdminStudioItemController::class, 'destroy']);
            Route::get('{studioId}/paint-colors', [AdminStudioPaintColorController::class, 'index']);
            Route::post('{studioId}/paint-colors', [AdminStudioPaintColorController::class, 'store']);
            Route::put('{studioId}/paint-colors/{id}', [AdminStudioPaintColorController::class, 'update']);
            Route::delete('{studioId}/paint-colors/{id}', [AdminStudioPaintColorController::class, 'destroy']);
        });

        Route::middleware('permission:users.manage')->prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('{id}', [AdminUserController::class, 'show']);
            Route::put('{id}', [AdminUserController::class, 'update']);
            Route::post('{id}/status', [AdminUserController::class, 'updateStatus']);
        });

        Route::middleware('permission:schedules.manage')->prefix('studios/{studioId}/schedule')->group(function () {
            Route::get('weekly', [AdminScheduleController::class, 'weekly']);
            Route::get('overview', [AdminScheduleController::class, 'overview']);
            Route::get('day', [AdminScheduleController::class, 'day']);
            Route::get('effective', [AdminScheduleController::class, 'effective']);
            Route::put('weekly', [AdminScheduleController::class, 'setWeekly']);
            Route::get('overrides', [AdminScheduleController::class, 'listOverrides']);
            Route::post('overrides', [AdminScheduleController::class, 'createOverride']);
            Route::put('overrides/{overrideId}', [AdminScheduleController::class, 'updateOverride']);
            Route::delete('overrides/{overrideId}', [AdminScheduleController::class, 'deleteOverride']);
            Route::get('blocks', [AdminScheduleController::class, 'listBlocks']);
            Route::post('blocks', [AdminScheduleController::class, 'createBlock']);
            Route::put('blocks/{blockId}', [AdminScheduleController::class, 'updateBlock']);
            Route::delete('blocks/{blockId}', [AdminScheduleController::class, 'deleteBlock']);
        });

        Route::middleware('permission:equipment.manage')->prefix('equipment')->group(function () {
            Route::get('/', [AdminEquipmentController::class, 'index']);
            Route::post('/', [AdminEquipmentController::class, 'store']);
            Route::get('{id}', [AdminEquipmentController::class, 'show']);
            Route::put('{id}', [AdminEquipmentController::class, 'update']);
            Route::post('{id}', [AdminEquipmentController::class, 'update']);
            Route::delete('{id}', [AdminEquipmentController::class, 'destroy']);
            Route::post('{equipmentId}/assign', [AdminEquipmentController::class, 'assign']);
            Route::get('{equipmentId}/availability', [AdminEquipmentController::class, 'availability']);
            Route::get('{id}/rentals', [AdminEquipmentController::class, 'rentals']);
            Route::delete('{equipmentId}/studios/{studioId}', [AdminEquipmentController::class, 'unassign']);
        });

        Route::middleware('permission:equipment.manage|accessories.manage|portable-bathrooms.manage|caravans.manage|generators.manage|bookings.manage')->prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('{id}', [AdminOrderController::class, 'show']);
            Route::post('{id}/status', [AdminOrderController::class, 'updateStatus']);
            Route::post('{id}/mark-paid', [AdminOrderController::class, 'markPaid']);
        });

        // Legacy aliases → unified orders
        Route::middleware('permission:equipment.manage|accessories.manage|portable-bathrooms.manage|caravans.manage|generators.manage|bookings.manage')->prefix('rental-orders')->group(function () {
            Route::get('/', [AdminRentalOrderController::class, 'index']);
            Route::get('{id}', [AdminRentalOrderController::class, 'show']);
            Route::post('{id}/status', [AdminRentalOrderController::class, 'updateStatus']);
            Route::post('{id}/mark-paid', [AdminRentalOrderController::class, 'markPaid']);
        });

        Route::middleware('permission:equipment.manage|accessories.manage')->prefix('equipment-rentals')->group(function () {
            Route::get('/', [AdminEquipmentRentalController::class, 'index']);
            Route::get('{id}', [AdminEquipmentRentalController::class, 'show']);
            Route::post('{id}/status', [AdminEquipmentRentalController::class, 'updateStatus']);
            Route::post('{id}/mark-paid', [AdminEquipmentRentalController::class, 'markPaid']);
        });

        Route::middleware('permission:hospitality.manage')->prefix('hospitality')->group(function () {
            Route::get('categories', [AdminHospitalityController::class, 'categories']);
            Route::post('categories', [AdminHospitalityController::class, 'storeCategory']);
            Route::get('items', [AdminHospitalityController::class, 'items']);
            Route::post('items', [AdminHospitalityController::class, 'storeItem']);
            Route::put('items/{id}', [AdminHospitalityController::class, 'updateItem']);
            Route::delete('items/{id}', [AdminHospitalityController::class, 'destroyItem']);
            Route::post('items/{itemId}/studios/{studioId}', [AdminHospitalityController::class, 'assign']);
            Route::delete('items/{itemId}/studios/{studioId}', [AdminHospitalityController::class, 'unassign']);
        });

        Route::middleware('permission:accessories.manage')->prefix('accessory-categories')->group(function () {
            Route::get('/', [AdminAccessoryCategoryController::class, 'index']);
            Route::post('/', [AdminAccessoryCategoryController::class, 'store']);
            Route::get('{id}', [AdminAccessoryCategoryController::class, 'show']);
            Route::put('{id}', [AdminAccessoryCategoryController::class, 'update']);
            Route::delete('{id}', [AdminAccessoryCategoryController::class, 'destroy']);
            Route::post('reorder', [AdminAccessoryCategoryController::class, 'reorder']);
            Route::post('{id}/toggle', [AdminAccessoryCategoryController::class, 'toggle']);
        });

        Route::middleware('permission:accessories.manage')->prefix('accessories')->group(function () {
            Route::get('/', [AdminAccessoryController::class, 'index']);
            Route::post('/', [AdminAccessoryController::class, 'store']);
            Route::get('{id}', [AdminAccessoryController::class, 'show']);
            Route::put('{id}', [AdminAccessoryController::class, 'update']);
            Route::post('{id}', [AdminAccessoryController::class, 'update']);
            Route::delete('{id}', [AdminAccessoryController::class, 'destroy']);
            Route::get('{id}/rentals', [AdminAccessoryController::class, 'rentals']);
        });

        Route::middleware('permission:portable-bathrooms.manage')->prefix('portable-bathrooms')->group(function () {
            Route::get('/', [AdminPortableBathroomController::class, 'index']);
            Route::post('/', [AdminPortableBathroomController::class, 'store']);
            Route::get('{id}', [AdminPortableBathroomController::class, 'show']);
            Route::put('{id}', [AdminPortableBathroomController::class, 'update']);
            Route::post('{id}', [AdminPortableBathroomController::class, 'update']);
            Route::delete('{id}', [AdminPortableBathroomController::class, 'destroy']);
            Route::get('{id}/orders', [AdminPortableBathroomController::class, 'orders']);
        });

        Route::middleware('permission:caravans.manage')->prefix('caravans')->group(function () {
            Route::get('/', [AdminCaravanController::class, 'index']);
            Route::post('/', [AdminCaravanController::class, 'store']);
            Route::get('{id}', [AdminCaravanController::class, 'show']);
            Route::put('{id}', [AdminCaravanController::class, 'update']);
            Route::post('{id}', [AdminCaravanController::class, 'update']);
            Route::delete('{id}', [AdminCaravanController::class, 'destroy']);
            Route::get('{id}/orders', [AdminCaravanController::class, 'orders']);
        });

        Route::middleware('permission:generators.manage')->prefix('generators')->group(function () {
            Route::get('/', [AdminGeneratorController::class, 'index']);
            Route::post('/', [AdminGeneratorController::class, 'store']);
            Route::get('{id}', [AdminGeneratorController::class, 'show']);
            Route::put('{id}', [AdminGeneratorController::class, 'update']);
            Route::post('{id}', [AdminGeneratorController::class, 'update']);
            Route::delete('{id}', [AdminGeneratorController::class, 'destroy']);
            Route::get('{id}/orders', [AdminGeneratorController::class, 'orders']);
        });

        // Legacy alias → unified orders
        Route::middleware('permission:equipment.manage|accessories.manage')->prefix('accessory-rentals')->group(function () {
            Route::get('/', [AdminAccessoryRentalController::class, 'index']);
            Route::get('{id}', [AdminAccessoryRentalController::class, 'show']);
            Route::post('{id}/status', [AdminAccessoryRentalController::class, 'updateStatus']);
            Route::post('{id}/mark-paid', [AdminAccessoryRentalController::class, 'markPaid']);
        });

        Route::middleware('permission:interior-designers.manage')->prefix('interior-designers')->group(function () {
            Route::get('/', [AdminInteriorDesignerController::class, 'index']);
            Route::post('/', [AdminInteriorDesignerController::class, 'store']);
            Route::get('{id}', [AdminInteriorDesignerController::class, 'show']);
            Route::put('{id}', [AdminInteriorDesignerController::class, 'update']);
            Route::post('{id}', [AdminInteriorDesignerController::class, 'update']);
            Route::delete('{id}', [AdminInteriorDesignerController::class, 'destroy']);
        });

        Route::middleware('permission:packages.manage')->prefix('packages')->group(function () {
            Route::get('/', [AdminPackageController::class, 'index']);
            Route::post('/', [AdminPackageController::class, 'store']);
            Route::get('{id}', [AdminPackageController::class, 'show']);
            Route::put('{id}', [AdminPackageController::class, 'update']);
            Route::delete('{id}', [AdminPackageController::class, 'destroy']);
        });

        Route::middleware('permission:pricing.manage')->prefix('pricing-rules')->group(function () {
            Route::get('/', [AdminPricingRuleController::class, 'index']);
            Route::post('/', [AdminPricingRuleController::class, 'store']);
            Route::get('{id}', [AdminPricingRuleController::class, 'show']);
            Route::put('{id}', [AdminPricingRuleController::class, 'update']);
            Route::delete('{id}', [AdminPricingRuleController::class, 'destroy']);
        });

        Route::middleware('permission:bookings.manage')->prefix('bookings')->group(function () {
            Route::get('/', [AdminBookingController::class, 'index']);
            Route::get('{id}', [AdminBookingController::class, 'show']);
            Route::post('{id}/status', [AdminBookingController::class, 'updateStatus']);
            Route::post('{id}/notes', [AdminBookingController::class, 'addNote']);
            Route::post('{id}/cancel', [AdminBookingController::class, 'cancel']);
            Route::post('{id}/mark-paid', [AdminBookingController::class, 'markPaid']);
        });

        Route::middleware('permission:payments.manage')->prefix('payments')->group(function () {
            Route::get('/', [AdminPaymentController::class, 'index']);
            Route::get('{id}', [AdminPaymentController::class, 'show']);
            Route::post('{paymentId}/refund', [AdminPaymentController::class, 'refund']);
        });

        Route::middleware('permission:cancellation-policies.manage')->prefix('cancellation-policies')->group(function () {
            Route::get('/', [AdminCancellationPolicyController::class, 'index']);
            Route::post('/', [AdminCancellationPolicyController::class, 'store']);
            Route::put('{id}', [AdminCancellationPolicyController::class, 'update']);
            Route::delete('{id}', [AdminCancellationPolicyController::class, 'destroy']);
        });

        Route::middleware('permission:reschedule-policies.manage')->prefix('reschedule-policies')->group(function () {
            Route::get('/', [AdminReschedulePolicyController::class, 'index']);
            Route::post('/', [AdminReschedulePolicyController::class, 'store']);
            Route::put('{id}', [AdminReschedulePolicyController::class, 'update']);
            Route::delete('{id}', [AdminReschedulePolicyController::class, 'destroy']);
        });

        Route::middleware('permission:loyalty.manage')->prefix('loyalty')->group(function () {
            Route::get('rules', [AdminLoyaltyController::class, 'rules']);
            Route::post('rules', [AdminLoyaltyController::class, 'storeRule']);
            Route::put('rules/{id}', [AdminLoyaltyController::class, 'updateRule']);
            Route::delete('rules/{id}', [AdminLoyaltyController::class, 'destroyRule']);
            Route::post('adjust', [AdminLoyaltyController::class, 'adjust']);
        });

        Route::middleware('permission:campaigns.manage')->prefix('campaigns')->group(function () {
            Route::get('/', [AdminCampaignController::class, 'index']);
            Route::post('/', [AdminCampaignController::class, 'store']);
            Route::post('{id}/execute', [AdminCampaignController::class, 'execute']);
        });

        Route::middleware('permission:promo-codes.manage')->prefix('promo-codes')->group(function () {
            Route::get('/', [AdminPromoCodeController::class, 'index']);
            Route::post('/', [AdminPromoCodeController::class, 'store']);
            Route::get('{id}', [AdminPromoCodeController::class, 'show']);
            Route::put('{id}', [AdminPromoCodeController::class, 'update']);
            Route::delete('{id}', [AdminPromoCodeController::class, 'destroy']);
        });

        Route::middleware('permission:segments.manage')->prefix('segments')->group(function () {
            Route::get('/', [AdminSegmentController::class, 'index']);
            Route::post('/', [AdminSegmentController::class, 'store']);
            Route::put('{id}', [AdminSegmentController::class, 'update']);
            Route::delete('{id}', [AdminSegmentController::class, 'destroy']);
            Route::get('{id}/preview', [AdminSegmentController::class, 'preview']);
        });

        Route::middleware('permission:notifications.manage')->prefix('notifications')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index']);
            Route::get('options', [AdminNotificationController::class, 'options']);
            Route::post('preview-audience', [AdminNotificationController::class, 'previewAudience']);
            Route::post('/', [AdminNotificationController::class, 'store']);
        });

        Route::middleware('permission:reviews.manage')->prefix('reviews')->group(function () {
            Route::get('/', [AdminReviewController::class, 'index']);
            Route::get('dimensions', [AdminReviewController::class, 'dimensions']);
            Route::post('dimensions', [AdminReviewController::class, 'storeDimension']);
            Route::post('{id}/moderate', [AdminReviewController::class, 'moderate']);
        });

        Route::middleware('permission:content.manage')->prefix('content')->group(function () {
            Route::get('banners', [AdminContentController::class, 'banners']);
            Route::post('banners', [AdminContentController::class, 'storeBanner']);
            Route::put('banners/{id}', [AdminContentController::class, 'updateBanner']);
            Route::delete('banners/{id}', [AdminContentController::class, 'destroyBanner']);
            Route::get('pages', [AdminContentController::class, 'pages']);
            Route::post('pages', [AdminContentController::class, 'storePage']);
            Route::put('pages/{id}', [AdminContentController::class, 'updatePage']);
            Route::delete('pages/{id}', [AdminContentController::class, 'destroyPage']);
        });

        Route::middleware('permission:content.manage')->prefix('catalog-section-covers')->group(function () {
            Route::get('/', [AdminCatalogSectionCoverController::class, 'index']);
            Route::post('/', [AdminCatalogSectionCoverController::class, 'update']);
            Route::put('/', [AdminCatalogSectionCoverController::class, 'update']);
        });

        Route::middleware('permission:reports.view')->prefix('reports')->group(function () {
            Route::get('revenue', [AdminReportController::class, 'revenue']);
            Route::get('utilization', [AdminReportController::class, 'utilization']);
            Route::get('loyalty', [AdminReportController::class, 'loyalty']);
        });

        Route::middleware('permission:audit.view')->prefix('audit-logs')->group(function () {
            Route::get('/', [AdminAuditController::class, 'index']);
        });

        Route::middleware('permission:roles.manage')->prefix('roles')->group(function () {
            Route::get('/', [AdminRoleController::class, 'index']);
            Route::get('permissions', [AdminRoleController::class, 'permissions']);
            Route::post('/', [AdminRoleController::class, 'store']);
            Route::put('{id}', [AdminRoleController::class, 'update']);
            Route::delete('{id}', [AdminRoleController::class, 'destroy']);
        });

        Route::middleware('permission:roles.manage')->prefix('admins')->group(function () {
            Route::get('/', [AdminStaffController::class, 'index']);
            Route::post('/', [AdminStaffController::class, 'store']);
            Route::get('{id}', [AdminStaffController::class, 'show']);
            Route::put('{id}', [AdminStaffController::class, 'update']);
            Route::put('{id}/roles', [AdminStaffController::class, 'syncRoles']);
            Route::put('{id}/permissions', [AdminStaffController::class, 'syncPermissions']);
            Route::post('{id}/permissions/give', [AdminStaffController::class, 'givePermissions']);
            Route::post('{id}/permissions/revoke', [AdminStaffController::class, 'revokePermissions']);
        });

        Route::middleware('permission:settings.manage')->prefix('settings')->group(function () {
            Route::get('/', [AdminAppSettingController::class, 'index']);
            Route::put('/', [AdminAppSettingController::class, 'update']);
        });
    });

    // Paymob is temporarily disabled (cash on delivery).
    // Route::prefix('webhooks')->group(function () {
    //     Route::post('paymob', [PaymobWebhookController::class, 'handle']);
    // });
});
