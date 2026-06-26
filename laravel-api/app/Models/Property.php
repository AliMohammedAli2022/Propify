<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'code',
        'type',
        'mode',
        'province',
        'area',
        'space',
        'rooms',
        'price',
        'status',
        'owner',
        'negotiable',
    ];

    protected $casts = [
        'negotiable' => 'boolean',
        'space' => 'decimal:2',
        'rooms' => 'integer',
    ];
}
