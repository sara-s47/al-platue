<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
}
