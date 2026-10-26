<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InteriorDesigner extends Model
{
    protected $fillable = [
        'name',
        'title',
        'phone',
        'image_path',
        'portfolio_url',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
