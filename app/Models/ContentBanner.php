<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBanner extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'link_type',
        'link_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'link_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
