<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsBlocklist extends Model
{
    protected $table = 'sms_blocklist';

    protected $fillable = [
        'phone_number',
        'reason',
    ];
}
