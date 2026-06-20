<?php

namespace App\Domains\Partner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerSettings extends Model
{
    protected $table = 'partner_settings';

    protected $fillable = [
        'partner_id',
        'notify_new_booking',
        'notify_cancellation',
        'notify_daily_summary',
        'notify_review_received',
        'notify_tour_status_change',
        'locale',
    ];

    protected function casts(): array
    {
        return [
            'notify_new_booking' => 'boolean',
            'notify_cancellation' => 'boolean',
            'notify_daily_summary' => 'boolean',
            'notify_review_received' => 'boolean',
            'notify_tour_status_change' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
