<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingsSeeder extends Seeder
{
    /**
     * Default app settings from DB design section 21.3.
     *
     * @var array<string, array{value: string, type: string}>
     */
    protected array $settings = [
        'booking_hold_minutes' => ['value' => '15', 'type' => 'int'],
        'minimum_booking_minutes' => ['value' => '60', 'type' => 'int'],
        'maximum_booking_minutes' => ['value' => '480', 'type' => 'int'],
        'max_daily_booking_days' => ['value' => '7', 'type' => 'int'],
        'booking_interval_minutes' => ['value' => '30', 'type' => 'int'],
        'cleanup_buffer_minutes' => ['value' => '15', 'type' => 'int'],
        'overtime_grace_minutes' => ['value' => '15', 'type' => 'int'],
        'overtime_interval_minutes' => ['value' => '15', 'type' => 'int'],
        'overtime_rate' => ['value' => '0', 'type' => 'float'],
        'timezone' => ['value' => 'Africa/Cairo', 'type' => 'string'],
    ];

    public function run(): void
    {
        foreach ($this->settings as $key => $setting) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ],
            );
        }
    }
}
