<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'type',
        'client',
        'amount',
        'reason',
        'property_code',
        'contract_code',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'date:Y-m-d',
    ];
}
