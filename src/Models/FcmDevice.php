<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class FcmDevice extends Model
{
    use HasUuids;

    protected $table = 'fcm_devices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'device_id',
        'token',
        'platform',
        'user_agent',
        'last_seen_at',
        'notifiable_type',
        'notifiable_id',
    ];

    protected $casts = [
        'last_seen_at' => 'immutable_datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
