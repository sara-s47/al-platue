<?php

namespace App\Enums;

enum HospitalityPricingModel: string
{
    case PerItem = 'per_item';
    case PerUnit = 'per_unit';
    case PerPerson = 'per_person';
    case PerPackage = 'per_package';
    case Included = 'included';
}
