<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'channel',
    'subject',
    'message',
    'status',
    'scheduled_at',
    'recipients_count',
])]
class MarketingCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'recipients_count' => 'integer',
        ];
    }
}
