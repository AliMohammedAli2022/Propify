<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'name',
        'role',
        'phone',
        'national_id',
        'stage',
        'source',
    ];
}
