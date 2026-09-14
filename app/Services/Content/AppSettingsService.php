<?php

namespace App\Services\Content;

use App\Repositories\Contracts\AppSettingRepositoryInterface;

class AppSettingsService
{
    public function __construct(
        protected AppSettingRepositoryInterface $appSettingRepository,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->appSettingRepository->findByKey($key);

        if (! $setting) {
            return $default;
        }

        return $this->castValue($setting->value, $setting->type);
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $existing = $this->appSettingRepository->findByKey($key);

        $payload = [
            'key' => $key,
            'value' => $this->serializeValue($value, $type),
            'type' => $type,
        ];

        if ($existing) {
            $this->appSettingRepository->update($existing->id, $payload);
        } else {
            $this->appSettingRepository->create($payload);
        }
    }

    public function getMany(array $keys): array
    {
        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = $this->get($key);
        }

        return $settings;
    }

    public function getBookingConfig(): array
    {
        $keys = [
            'booking_hold_minutes',
            'minimum_booking_minutes',
            'maximum_booking_minutes',
            'max_daily_booking_days',
            'booking_interval_minutes',
            'cleanup_buffer_minutes',
            'overtime_grace_minutes',
            'overtime_interval_minutes',
            'overtime_rate',
            'timezone',
        ];

        $config = $this->getMany($keys);

        return [
            'booking_hold_minutes' => (int) ($config['booking_hold_minutes'] ?? 15),
            'minimum_booking_minutes' => (int) ($config['minimum_booking_minutes'] ?? 60),
            'maximum_booking_minutes' => (int) ($config['maximum_booking_minutes'] ?? 480),
            'max_daily_booking_days' => (int) ($config['max_daily_booking_days'] ?? 7),
            'booking_interval_minutes' => (int) ($config['booking_interval_minutes'] ?? 30),
            'cleanup_buffer_minutes' => (int) ($config['cleanup_buffer_minutes'] ?? 15),
            'overtime_grace_minutes' => (int) ($config['overtime_grace_minutes'] ?? 15),
            'overtime_interval_minutes' => (int) ($config['overtime_interval_minutes'] ?? 15),
            'overtime_rate' => (float) ($config['overtime_rate'] ?? 0),
            'timezone' => (string) ($config['timezone'] ?? config('app.timezone')),
        ];
    }

    protected function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    protected function serializeValue(mixed $value, string $type): string
    {
        if ($type === 'json') {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
