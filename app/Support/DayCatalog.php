<?php

namespace App\Support;

use App\Models\Caravan;
use App\Models\CaravanOrderItem;
use App\Models\Generator;
use App\Models\GeneratorOrderItem;
use App\Models\PortableBathroom;
use App\Models\PortableBathroomOrderItem;

final class DayCatalog
{
    public const PORTABLE_BATHROOM = 'portable_bathroom';

    public const CARAVAN = 'caravan';

    public const GENERATOR = 'generator';

    /** @return list<string> */
    public static function types(): array
    {
        return [self::PORTABLE_BATHROOM, self::CARAVAN, self::GENERATOR];
    }

    public static function modelClass(string $type): string
    {
        return match ($type) {
            self::PORTABLE_BATHROOM => PortableBathroom::class,
            self::CARAVAN => Caravan::class,
            self::GENERATOR => Generator::class,
            default => throw new \InvalidArgumentException("Unknown day catalog type: {$type}"),
        };
    }

    public static function orderItemClass(string $type): string
    {
        return match ($type) {
            self::PORTABLE_BATHROOM => PortableBathroomOrderItem::class,
            self::CARAVAN => CaravanOrderItem::class,
            self::GENERATOR => GeneratorOrderItem::class,
            default => throw new \InvalidArgumentException("Unknown day catalog type: {$type}"),
        };
    }

    public static function productFk(string $type): string
    {
        return match ($type) {
            self::PORTABLE_BATHROOM => 'portable_bathroom_id',
            self::CARAVAN => 'caravan_id',
            self::GENERATOR => 'generator_id',
            default => throw new \InvalidArgumentException("Unknown day catalog type: {$type}"),
        };
    }

    public static function storageFolder(string $type): string
    {
        return match ($type) {
            self::PORTABLE_BATHROOM => 'portable-bathrooms',
            self::CARAVAN => 'caravans',
            self::GENERATOR => 'generators',
            default => 'catalog',
        };
    }

    public static function permission(string $type): string
    {
        return match ($type) {
            self::PORTABLE_BATHROOM => 'portable-bathrooms.manage',
            self::CARAVAN => 'caravans.manage',
            self::GENERATOR => 'generators.manage',
            default => throw new \InvalidArgumentException("Unknown day catalog type: {$type}"),
        };
    }
}
