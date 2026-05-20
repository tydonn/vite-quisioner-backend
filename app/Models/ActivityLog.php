<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $connection = 'mysql';

    protected $table = 'activity_logs';

    protected $fillable = [
        'module',
        'action',
        'entity_type',
        'entity_id',
        'actor_id',
        'actor_name',
        'actor_email',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
