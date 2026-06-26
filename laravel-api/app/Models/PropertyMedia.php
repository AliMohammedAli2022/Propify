<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyMedia extends Model
{
    protected $table = 'property_media';

    protected $fillable = [
        'property_code',
        'kind',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];
}
