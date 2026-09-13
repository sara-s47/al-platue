<?php

namespace App\Enums;

enum NotificationAudience: string
{
    case All = 'all';
    case WithBookings = 'with_bookings';
    case WithoutBookings = 'without_bookings';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All customers',
            self::WithBookings => 'Customers with previous bookings',
            self::WithoutBookings => 'Customers with no bookings',
            self::Custom => 'Custom user list',
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
