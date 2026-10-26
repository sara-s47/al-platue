<?php

namespace App\Models;

use App\Enums\CatalogSection;
use Illuminate\Database\Eloquent\Model;

class CatalogSectionCover extends Model
{
    protected $fillable = [
        'section',
        'cover_image',
    ];

    protected function casts(): array
    {
        return [
            'section' => CatalogSection::class,
        ];
    }
}
