<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case General = 'general';
    case Promo = 'promo';
    case Loyalty = 'loyalty';
    case Booking = 'booking';
    case System = 'system';
    case Announcement = 'announcement';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Promo => 'Promo',
            self::Loyalty => 'Loyalty',
            self::Booking => 'Booking',
            self::System => 'System',
            self::Announcement => 'Announcement',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
