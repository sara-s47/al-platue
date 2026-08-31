<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $fillable = [
        'name',
        'filters_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
