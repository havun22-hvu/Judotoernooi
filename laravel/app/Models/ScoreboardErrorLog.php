<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreboardErrorLog extends Model
{
    protected $fillable = [
        'message',
        'stack',
        'screen',
        'app_timestamp',
        'app_version',
        'fatal',
        'device',
        'platform_version',
        'device_toegang_id',
    ];

    protected $casts = [
        'fatal' => 'boolean',
        'app_timestamp' => 'datetime',
    ];

    public function deviceToegang(): BelongsTo
    {
        return $this->belongsTo(DeviceToegang::class);
    }
}
