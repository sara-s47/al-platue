<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\PricingRuleType;
use App\Models\Equipment;
use App\Models\HospitalityItem;
use App\Models\Package;
use App\Models\PricingRule;
use App\Models\Studio;
use App\Models\StudioBlock;
use App\Models\StudioSchedule;
use App\Models\StudioScheduleOverride;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BookingAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $studios = Studio::query()->orderBy('id')->get();

        if ($studios->isEmpty()) {
            $this->command?->warn('No studios found. Run DemoDataSeeder first.');

            return;
        }

        $equipment = Equipment::query()
            ->where('status', EquipmentStatus::Active)
            ->orderBy('id')
            ->get();

        $hospitality = HospitalityItem::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($studios as $studio) {
            $studio->update(['is_active' => true]);
            $this->seedWideWeeklyHours($studio);
            $this->openFutureClosedOverrides($studio);
            $this->clearFutureBlocks($studio);
            $this->ensureStudioInventory($studio, $equipment, $hospitality);
            $this->ensureBasePricing($studio);
            $this->seedPackages($studio, $equipment, $hospitality);
        }

        $this->command?->info("Booking test data ready for {$studios->count()} studio(s).");
        $this->command?->info('Weekly hours: 08:00–23:00 every day. Extra packages include equipment and hospitality add-ons.');
    }

    protected function seedWideWeeklyHours(Studio $studio): void
    {
        foreach (range(0, 6) as $dayOfWeek) {
            StudioSchedule::updateOrCreate(
                [
                    'studio_id' => $studio->id,
                    'day_of_week' => $dayOfWeek,
                ],
                [
                    'open_time' => '08:00:00',
                    'close_time' => '23:00:00',
                    'is_closed' => false,
                ],
            );
        }
    }

    protected function openFutureClosedOverrides(Studio $studio): void
    {
        StudioScheduleOverride::query()
            ->where('studio_id', $studio->id)
            ->where('date', '>=', now()->toDateString())
            ->where('is_closed', true)
            ->delete();
    }

    protected function clearFutureBlocks(Studio $studio): void
    {
        StudioBlock::query()
            ->where('studio_id', $studio->id)
            ->where('end_at', '>=', now())
            ->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Equipment>  $equipment
     * @param  \Illuminate\Support\Collection<int, HospitalityItem>  $hospitality
     */
    protected function ensureStudioInventory($studio, $equipment, $hospitality): void
    {
        foreach ($equipment as $item) {
            DB::table('studio_equipment')->updateOrInsert(
                [
                    'studio_id' => $studio->id,
                    'equipment_id' => $item->id,
                ],
                ['quantity' => max(3, (int) $item->quantity)],
            );
        }

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

    protected function ensureBasePricing(Studio $studio): void
    {
        $values = [
            'start_time' => null,
            'end_time' => null,
            'price_per_hour' => 500.00,
            'priority' => 0,
            'is_active' => true,
        ];

        if (Schema::hasColumn('pricing_rules', 'price_per_day')) {
            $values['price_per_day'] = 3500.00;
        }

        PricingRule::updateOrCreate(
            [
                'studio_id' => $studio->id,
                'rule_type' => PricingRuleType::Base,
                'day_of_week' => null,
                'specific_date' => null,
            ],
            $values,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Equipment>  $equipment
     * @param  \Illuminate\Support\Collection<int, HospitalityItem>  $hospitality
     */
    protected function seedPackages(Studio $studio, $equipment, $hospitality): void
    {
        $byEquipmentName = $equipment->keyBy('name');
        $byHospitalityName = $hospitality->keyBy('name');

        $packages = [
            [
                'name' => 'Express Hour',
                'description' => 'One-hour session with lighting included. Add extra camera or drinks on quote.',
                'duration_minutes' => 60,
                'price' => 550.00,
                'equipment' => ['LED Softbox Lighting Kit' => 1],
                'hospitality' => ['Coffee & Tea' => 1],
            ],
            [
                'name' => 'Portrait Plus (90 min)',
                'description' => 'Hour and a half with camera + lighting included. Extra teleprompter and water available as add-ons.',
                'duration_minutes' => 90,
                'price' => 800.00,
                'equipment' => [
                    'Sony A7 IV Camera' => 1,
                    'LED Softbox Lighting Kit' => 1,
                ],
                'hospitality' => [
                    'Coffee & Tea' => 1,
                    'Bottled Water' => 4,
                ],
            ],
            [
                'name' => 'Starter Session (2h)',
                'description' => 'Two-hour session with included lighting and coffee.',
                'duration_minutes' => 120,
                'price' => 900.00,
                'equipment' => ['LED Softbox Lighting Kit' => 1],
                'hospitality' => ['Coffee & Tea' => 1],
            ],
            [
                'name' => 'Evening Production (3h)',
                'description' => 'Three-hour evening package with camera, lighting, green screen, snacks, and assistant.',
                'duration_minutes' => 180,
                'price' => 1400.00,
                'equipment' => [
                    'Sony A7 IV Camera' => 1,
                    'LED Softbox Lighting Kit' => 1,
                    'Chroma Key Backdrop' => 1,
                ],
                'hospitality' => [
                    'Coffee & Tea' => 1,
                    'Snack Platter' => 1,
                    'Studio Assistant' => 1,
                ],
            ],
            [
                'name' => 'Half Day (4h)',
                'description' => 'Four-hour session with camera kit and snack platter.',
                'duration_minutes' => 240,
                'price' => 1600.00,
                'equipment' => [
                    'Sony A7 IV Camera' => 1,
                    'LED Softbox Lighting Kit' => 1,
                ],
                'hospitality' => [
                    'Coffee & Tea' => 1,
                    'Snack Platter' => 1,
                ],
            ],
            [
                'name' => 'Creator Day (6h)',
                'description' => 'Six-hour content day with camera, lighting, teleprompter, mic, water, snacks, and assistant.',
                'duration_minutes' => 360,
                'price' => 2200.00,
                'equipment' => [
                    'Sony A7 IV Camera' => 1,
                    'LED Softbox Lighting Kit' => 1,
                    'Teleprompter Pro' => 1,
                    'Shure SM7B Microphone' => 1,
                ],
                'hospitality' => [
                    'Coffee & Tea' => 1,
                    'Bottled Water' => 8,
                    'Snack Platter' => 1,
                    'Studio Assistant' => 1,
                ],
            ],
            [
                'name' => 'Full Day (8h)',
                'description' => 'Full-day production package with premium equipment and assistant.',
                'duration_minutes' => 480,
                'price' => 2800.00,
                'equipment' => [
                    'Sony A7 IV Camera' => 1,
                    'LED Softbox Lighting Kit' => 1,
                    'Teleprompter Pro' => 1,
                    'Chroma Key Backdrop' => 1,
                ],
                'hospitality' => [
                    'Coffee & Tea' => 1,
                    'Snack Platter' => 1,
                    'Studio Assistant' => 1,
                ],
            ],
        ];

        $validFrom = now()->subDay()->toDateString();
        $validTo = now()->addYear()->toDateString();

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
                    'valid_from' => $validFrom,
                    'valid_to' => $validTo,
                    'is_active' => true,
                ],
            );

            DB::table('package_equipment')->where('package_id', $package->id)->delete();
            DB::table('package_hospitality')->where('package_id', $package->id)->delete();

            foreach ($definition['equipment'] as $name => $quantity) {
                $item = $byEquipmentName->get($name);
                if (! $item) {
                    continue;
                }

                DB::table('package_equipment')->insert([
                    'package_id' => $package->id,
                    'equipment_id' => $item->id,
                    'quantity' => $quantity,
                ]);
            }

            foreach ($definition['hospitality'] as $name => $quantity) {
                $item = $byHospitalityName->get($name);
                if (! $item) {
                    continue;
                }

                DB::table('package_hospitality')->insert([
                    'package_id' => $package->id,
                    'hospitality_item_id' => $item->id,
                    'quantity' => $quantity,
                ]);
            }
        }
    }
}
