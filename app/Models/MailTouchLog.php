<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTouchLog extends Model
{
    protected $table = 'v2_mail_touch_logs';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
