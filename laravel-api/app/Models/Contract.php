<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'code',
        'property_code',
        'client',
        'kind',
        'total',
        'paid',
        'due',
        'commission',
        'status',
    ];
}
