<?php

namespace App\Enums;

enum CatalogSection: string
{
    case Studios = 'studios';
    case Accessories = 'accessories';
    case Equipment = 'equipment';
    case Caravans = 'caravans';
    case PortableBathrooms = 'portable_bathrooms';
    case Generators = 'generators';
    case InteriorDesigners = 'interior_designers';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
