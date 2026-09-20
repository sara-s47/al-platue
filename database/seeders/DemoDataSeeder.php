<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\HospitalityPricingModel;
use App\Enums\LoyaltyRuleType;
use App\Enums\PricingRuleType;
use App\Models\CancellationPolicy;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Equipment;
use App\Models\HospitalityCategory;
use App\Models\HospitalityItem;
use App\Models\LoyaltyRule;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\ReschedulePolicy;
use App\Models\ReviewDimension;
use App\Models\Studio;
use App\Models\StudioImage;
use App\Models\StudioSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $equipment = $this->seedEquipment();
        $hospitality = $this->seedHospitality();

        $this->seedPolicies();
        $this->seedLoyaltyRules();
        $this->seedReviewDimensions();
        $this->seedContentPages();

        foreach ($this->studioDefinitions($categories) as $definition) {
            $studio = Studio::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'category_id' => $categories[$definition['category']]->id,
                    'description' => $definition['description'],
                    'capacity' => $definition['capacity'],
                    'address' => $definition['address'],
                    'latitude' => $definition['latitude'],
                    'longitude' => $definition['longitude'],
                    'rules' => $definition['rules'],
                    'is_active' => true,
                ],
            );

            $this->seedStudioImages($studio);
            $this->seedWeeklySchedule($studio);
            $this->seedStudioEquipment($studio, $equipment);
            $this->seedStudioHospitality($studio, $hospitality);
            $this->seedPricingRules($studio);
            $this->seedPackages($studio, $equipment, $hospitality);
        }
    }

    /**
     * @return array<string, Category>
     */
    protected function seedCategories(): array
    {
        $definitions = [
            'photography' => [
                'name' => 'Photography',
                'description' => 'Professional photo studios with controlled lighting and backdrops.',
                'sort_order' => 1,
            ],
            'podcast' => [
                'name' => 'Podcast',
                'description' => 'Sound-treated rooms for podcast and voice-over recording.',
                'sort_order' => 2,
            ],
            'video' => [
                'name' => 'Video Production',
                'description' => 'Spacious studios equipped for video shoots and live streaming.',
                'sort_order' => 3,
            ],
            'music' => [
                'name' => 'Music Recording',
                'description' => 'Acoustically treated rooms for music and audio production.',
                'sort_order' => 4,
            ],
        ];

        $categories = [];

        foreach ($definitions as $key => $definition) {
            $categories[$key] = Category::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Equipment>
     */
    protected function seedEquipment(): array
    {
        $definitions = [
            'camera' => [
                'name' => 'Sony A7 IV Camera',
                'description' => 'Full-frame mirrorless camera with 28-70mm lens.',
                'price' => 250.00,
                'quantity' => 4,
                'is_included' => false,
            ],
            'lighting' => [
                'name' => 'LED Softbox Lighting Kit',
                'description' => 'Three-point lighting setup with diffusers and stands.',
                'price' => 150.00,
                'quantity' => 6,
                'is_included' => true,
            ],
            'microphone' => [
                'name' => 'Shure SM7B Microphone',
                'description' => 'Broadcast-quality dynamic microphone with boom arm.',
                'price' => 100.00,
                'quantity' => 8,
                'is_included' => true,
            ],
            'teleprompter' => [
                'name' => 'Teleprompter Pro',
                'description' => '17-inch teleprompter compatible with DSLR and mirrorless cameras.',
                'price' => 120.00,
                'quantity' => 3,
                'is_included' => false,
            ],
            'green_screen' => [
                'name' => 'Chroma Key Backdrop',
                'description' => 'Collapsible green screen with stand and clips.',
                'price' => 80.00,
                'quantity' => 5,
                'is_included' => true,
            ],
        ];

        $equipment = [];

        foreach ($definitions as $key => $definition) {
            $equipment[$key] = Equipment::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'description' => $definition['description'],
                    'price' => $definition['price'],
                    'quantity' => $definition['quantity'],
                    'status' => EquipmentStatus::Active,
                    'is_included' => $definition['is_included'],
                ],
            );
        }

        return $equipment;
    }

    /**
     * @return array<string, HospitalityItem>
     */
    protected function seedHospitality(): array
    {
        $beverages = HospitalityCategory::updateOrCreate(
            ['name' => 'Beverages'],
            ['description' => 'Hot and cold drinks for studio sessions.', 'is_active' => true],
        );

        $snacks = HospitalityCategory::updateOrCreate(
            ['name' => 'Snacks'],
            ['description' => 'Light snacks and refreshments.', 'is_active' => true],
        );

        $services = HospitalityCategory::updateOrCreate(
            ['name' => 'Services'],
            ['description' => 'On-site support and add-on services.', 'is_active' => true],
        );

        $definitions = [
            'coffee' => [
                'category_id' => $beverages->id,
                'name' => 'Coffee & Tea',
                'description' => 'Unlimited coffee and tea during your session.',
                'pricing_model' => HospitalityPricingModel::Included,
                'price' => 0,
                'quantity' => 100,
            ],
            'water' => [
                'category_id' => $beverages->id,
                'name' => 'Bottled Water',
                'description' => 'Chilled bottled water.',
                'pricing_model' => HospitalityPricingModel::PerPerson,
                'price' => 15.00,
                'quantity' => 200,
            ],
            'snack_platter' => [
                'category_id' => $snacks->id,
                'name' => 'Snack Platter',
                'description' => 'Assorted snacks for up to 5 people.',
                'pricing_model' => HospitalityPricingModel::PerItem,
                'price' => 120.00,
                'quantity' => 30,
            ],
            'assistant' => [
                'category_id' => $services->id,
                'name' => 'Studio Assistant',
                'description' => 'On-site assistant for equipment setup and support.',
                'pricing_model' => HospitalityPricingModel::PerUnit,
                'price' => 200.00,
                'quantity' => 10,
            ],
        ];

        $items = [];

        foreach ($definitions as $key => $definition) {
            $items[$key] = HospitalityItem::updateOrCreate(
                ['name' => $definition['name'], 'category_id' => $definition['category_id']],
                [
                    'description' => $definition['description'],
                    'pricing_model' => $definition['pricing_model'],
                    'price' => $definition['price'],
                    'quantity' => $definition['quantity'],
                    'is_active' => true,
                ],
            );
        }

        return $items;
    }

    protected function seedPolicies(): void
    {
        CancellationPolicy::updateOrCreate(
            ['name' => 'Full refund (72h+)'],
            [
                'hours_before' => 72,
                'refund_percentage' => 100.00,
                'cancellation_fee' => 0,
                'is_active' => true,
            ],
        );

        CancellationPolicy::updateOrCreate(
            ['name' => 'Partial refund (48h+)'],
            [
                'hours_before' => 48,
                'refund_percentage' => 50.00,
                'cancellation_fee' => 50.00,
                'is_active' => true,
            ],
        );

        CancellationPolicy::updateOrCreate(
            ['name' => 'Limited refund (24h+)'],
            [
                'hours_before' => 24,
                'refund_percentage' => 25.00,
                'cancellation_fee' => 100.00,
                'is_active' => true,
            ],
        );

        // Soft-disable legacy demo names if present
        CancellationPolicy::query()
            ->whereIn('name', ['Flexible (24h)', 'Standard (48h)', 'Strict (72h)', 'Flexible (24h+)', 'Standard (48h+)', 'Strict (72h+)'])
            ->update(['is_active' => false]);


        ReschedulePolicy::updateOrCreate(
            ['name' => 'Standard Reschedule'],
            [
                'min_notice_hours' => 24,
                'max_reschedules' => 2,
                'fee' => 0,
                'is_active' => true,
            ],
        );

        ReschedulePolicy::updateOrCreate(
            ['name' => 'Premium Reschedule'],
            [
                'min_notice_hours' => 12,
                'max_reschedules' => 3,
                'fee' => 75.00,
                'is_active' => true,
            ],
        );
    }

    protected function seedLoyaltyRules(): void
    {
        $rules = [
            [
                'name' => 'Welcome Bonus',
                'rule_type' => LoyaltyRuleType::Welcome,
                'value' => null,
                'points' => 100,
                'min_redemption_points' => null,
                'max_redemption_amount' => null,
                'max_redemption_percentage' => null,
            ],
            [
                'name' => 'Completed Booking Reward',
                'rule_type' => LoyaltyRuleType::Booking,
                'value' => null,
                'points' => 50,
                'min_redemption_points' => null,
                'max_redemption_amount' => null,
                'max_redemption_percentage' => null,
            ],
            [
                'name' => 'Spending Points (1 EGP = 1 pt)',
                'rule_type' => LoyaltyRuleType::Spending,
                'value' => 1.00,
                'points' => 1,
                'min_redemption_points' => null,
                'max_redemption_amount' => null,
                'max_redemption_percentage' => null,
            ],
            [
                'name' => 'Points Redemption',
                'rule_type' => LoyaltyRuleType::Redemption,
                'value' => 1.00,
                'points' => 100,
                'min_redemption_points' => 500,
                'max_redemption_amount' => 500.00,
                'max_redemption_percentage' => 25.00,
            ],
            [
                'name' => 'Points Expiration (12 months)',
                'rule_type' => LoyaltyRuleType::Expiration,
                'value' => 365.00,
                'points' => 0,
                'min_redemption_points' => null,
                'max_redemption_amount' => null,
                'max_redemption_percentage' => null,
            ],
        ];

        foreach ($rules as $rule) {
            LoyaltyRule::updateOrCreate(
                ['name' => $rule['name']],
                array_merge($rule, ['is_active' => true]),
            );
        }
    }

    protected function seedReviewDimensions(): void
    {
        foreach (['Cleanliness', 'Equipment Quality', 'Staff Support', 'Value for Money', 'Overall Experience'] as $name) {
            ReviewDimension::updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }

    protected function seedContentPages(): void
    {
        $pages = [
            'terms' => [
                'title' => 'Terms & Conditions',
                'content' => '<p>Welcome to El-Platue Studios. By booking a studio session you agree to our terms of service, cancellation policy, and studio rules.</p>',
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'content' => '<p>We respect your privacy. This policy explains how we collect, use, and protect your personal information.</p>',
            ],
            'about' => [
                'title' => 'About Us',
                'content' => '<p>El-Platue Studios offers premium creative spaces for photography, podcasting, video production, and music recording in Cairo.</p>',
            ],
            'faq' => [
                'title' => 'FAQ',
                'content' => '<p><strong>How do I book?</strong> Select a studio, choose your time slot, and confirm your booking through the app.</p>',
            ],
            'contact' => [
                'title' => 'Contact Us',
                'content' => '<p>Email: support@el-platue.test | Phone: +20 100 000 0000</p>',
            ],
        ];

        foreach ($pages as $key => $page) {
            ContentPage::updateOrCreate(
                ['key' => $key],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<string, Category>  $categories
     * @return list<array<string, mixed>>
     */
    protected function studioDefinitions(array $categories): array
    {
        return [
            [
                'category' => 'photography',
                'name' => 'Plateau Portrait Studio',
                'description' => 'Compact portrait studio with seamless backdrops and professional lighting.',
                'capacity' => 6,
                'address' => '12 Nile Corniche, Zamalek, Cairo',
                'latitude' => 30.0626000,
                'longitude' => 31.2197000,
                'rules' => "No smoking inside the studio.\nHandle equipment with care.\nClean up after your session.",
            ],
            [
                'category' => 'photography',
                'name' => 'Plateau Fashion Loft',
                'description' => 'Open loft space ideal for fashion and editorial photography.',
                'capacity' => 12,
                'address' => '45 Tahrir Square, Downtown Cairo',
                'latitude' => 30.0444000,
                'longitude' => 31.2357000,
                'rules' => "Maximum 12 people on set.\nNo food near equipment.\nReturn props to storage after use.",
            ],
            [
                'category' => 'podcast',
                'name' => 'Plateau Podcast Room A',
                'description' => 'Sound-treated podcast room for up to 4 hosts with mixing desk.',
                'capacity' => 4,
                'address' => '8 Maadi Ring Road, Maadi, Cairo',
                'latitude' => 29.9608000,
                'longitude' => 31.2569000,
                'rules' => "Keep doors closed during recording.\nUse provided headphones only.\nNo loud phone notifications.",
            ],
            [
                'category' => 'video',
                'name' => 'Plateau Video Stage',
                'description' => 'Large video stage with green screen, lighting grid, and control room.',
                'capacity' => 20,
                'address' => '102 New Cairo Business District, Cairo',
                'latitude' => 30.0287000,
                'longitude' => 31.4976000,
                'rules' => "Crew must wear soft-soled shoes.\nAll cables must be taped down.\nFollow safety briefing before shoot.",
            ],
            [
                'category' => 'music',
                'name' => 'Plateau Music Booth',
                'description' => 'Acoustic booth for vocal recording and small ensemble sessions.',
                'capacity' => 5,
                'address' => '27 Heliopolis Square, Heliopolis, Cairo',
                'latitude' => 30.0875000,
                'longitude' => 31.3249000,
                'rules' => "No drums without prior approval.\nMonitor volume levels.\nStore instruments in designated area.",
            ],
        ];
    }

    protected function seedStudioImages(Studio $studio): void
    {
        foreach (range(1, 3) as $sortOrder) {
            StudioImage::updateOrCreate(
                [
                    'studio_id' => $studio->id,
                    'sort_order' => $sortOrder,
                ],
                [
                    'path' => "placeholders/studios/{$studio->id}/image-{$sortOrder}.jpg",
                    'alt_text' => "{$studio->name} - Image {$sortOrder}",
                ],
            );
        }
    }

    protected function seedWeeklySchedule(Studio $studio): void
    {
        $weeklyHours = [
            0 => ['open' => '10:00:00', 'close' => '22:00:00', 'closed' => false],
            1 => ['open' => '09:00:00', 'close' => '23:00:00', 'closed' => false],
            2 => ['open' => '09:00:00', 'close' => '23:00:00', 'closed' => false],
            3 => ['open' => '09:00:00', 'close' => '23:00:00', 'closed' => false],
            4 => ['open' => '09:00:00', 'close' => '23:00:00', 'closed' => false],
            5 => ['open' => '09:00:00', 'close' => '23:00:00', 'closed' => false],
            6 => ['open' => '10:00:00', 'close' => '20:00:00', 'closed' => false],
        ];

        foreach ($weeklyHours as $dayOfWeek => $hours) {
            StudioSchedule::updateOrCreate(
                [
                    'studio_id' => $studio->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'open_time' => $hours['open'],
                    'close_time' => $hours['close'],
                    'is_closed' => $hours['closed'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Equipment>  $equipment
     */
    protected function seedStudioEquipment(Studio $studio, array $equipment): void
    {
        $assignments = [
            'camera' => 2,
            'lighting' => 1,
            'microphone' => 2,
            'teleprompter' => 1,
            'green_screen' => 1,
        ];

        foreach ($assignments as $key => $quantity) {
            DB::table('studio_equipment')->updateOrInsert(
                [
                    'studio_id' => $studio->id,
                    'equipment_id' => $equipment[$key]->id,
                ],
                ['quantity' => $quantity],
            );
        }
    }

    /**
     * @param  array<string, HospitalityItem>  $hospitality
     */
    protected function seedStudioHospitality(Studio $studio, array $hospitality): void
    {
        foreach ($hospitality as $item) {
            DB::table('studio_hospitality')->updateOrInsert(
                [
                    'studio_id' => $studio->id,
                    'hospitality_item_id' => $item->id,
                ],
                [],
            );
        }
    }

    protected function seedPricingRules(Studio $studio): void
    {
        PricingRule::updateOrCreate(
            [
                'studio_id' => $studio->id,
                'rule_type' => PricingRuleType::Base,
                'day_of_week' => null,
                'specific_date' => null,
            ],
            [
                'start_time' => null,
                'end_time' => null,
                'price_per_hour' => 500.00,
                'price_per_day' => 3500.00,
                'priority' => 0,
                'is_active' => true,
            ],
        );

        PricingRule::updateOrCreate(
            [
                'studio_id' => $studio->id,
                'rule_type' => PricingRuleType::Weekend,
                'day_of_week' => 5,
            ],
            [
                'start_time' => null,
                'end_time' => null,
                'specific_date' => null,
                'price_per_hour' => 650.00,
                'price_per_day' => 4500.00,
                'priority' => 10,
                'is_active' => true,
            ],
        );

        PricingRule::updateOrCreate(
            [
                'studio_id' => $studio->id,
                'rule_type' => PricingRuleType::Weekend,
                'day_of_week' => 6,
            ],
            [
                'start_time' => null,
                'end_time' => null,
                'specific_date' => null,
                'price_per_hour' => 650.00,
                'price_per_day' => 4500.00,
                'priority' => 10,
                'is_active' => true,
            ],
        );

        PricingRule::updateOrCreate(
            [
                'studio_id' => $studio->id,
                'rule_type' => PricingRuleType::Peak,
                'day_of_week' => null,
            ],
            [
                'start_time' => '18:00:00',
                'end_time' => '23:00:00',
                'specific_date' => null,
                'price_per_hour' => 750.00,
                'price_per_day' => null,
                'priority' => 20,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array<string, Equipment>  $equipment
     * @param  array<string, HospitalityItem>  $hospitality
     */
    protected function seedPackages(Studio $studio, array $equipment, array $hospitality): void
    {
        $packages = [
            [
                'name' => 'Starter Session (2h)',
                'description' => 'Two-hour session with included lighting and one add-on item.',
                'duration_minutes' => 120,
                'price' => 900.00,
                'equipment' => ['lighting' => 1],
                'hospitality' => ['coffee' => 1],
            ],
            [
                'name' => 'Half Day (4h)',
                'description' => 'Four-hour session with camera kit and snack platter.',
                'duration_minutes' => 240,
                'price' => 1600.00,
                'equipment' => ['camera' => 1, 'lighting' => 1],
                'hospitality' => ['coffee' => 1, 'snack_platter' => 1],
            ],
            [
                'name' => 'Full Day (8h)',
                'description' => 'Full-day production package with premium equipment and assistant.',
                'duration_minutes' => 480,
                'price' => 2800.00,
                'equipment' => ['camera' => 1, 'lighting' => 1, 'teleprompter' => 1, 'green_screen' => 1],
                'hospitality' => ['coffee' => 1, 'snack_platter' => 1, 'assistant' => 1],
            ],
        ];

        foreach ($packages as $definition) {
            $package = Package::updateOrCreate(
                [
                    'studio_id' => $studio->id,
                    'name' => $definition['name'],
                ],
                [
                    'description' => $definition['description'],
                    'duration_minutes' => $definition['duration_minutes'],
                    'price' => $definition['price'],
                    'valid_from' => now()->startOfYear()->toDateString(),
                    'valid_to' => now()->endOfYear()->toDateString(),
                    'is_active' => true,
                ],
            );

            foreach ($definition['equipment'] as $key => $quantity) {
                DB::table('package_equipment')->updateOrInsert(
                    [
                        'package_id' => $package->id,
                        'equipment_id' => $equipment[$key]->id,
                    ],
                    ['quantity' => $quantity],
                );
            }

            foreach ($definition['hospitality'] as $key => $quantity) {
                DB::table('package_hospitality')->updateOrInsert(
                    [
                        'package_id' => $package->id,
                        'hospitality_item_id' => $hospitality[$key]->id,
                    ],
                    ['quantity' => $quantity],
                );
            }
        }
    }
}
