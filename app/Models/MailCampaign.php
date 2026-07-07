<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailCampaign extends Model
{
    protected $table = 'v2_mail_campaigns';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'scope_payload' => 'array',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MailCampaignRecipient::class, 'campaign_id');
    }
}
