<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_phone',
        'company_email',
        'company_address',
        'default_currency',
        'default_commission_rate',
    ];
}
