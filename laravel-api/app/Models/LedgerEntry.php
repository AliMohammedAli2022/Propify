<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = [
        'code',
        'direction',
        'amount',
        'description',
        'entry_date',
    ];

    protected $casts = [
        'entry_date' => 'date:Y-m-d',
    ];
}
