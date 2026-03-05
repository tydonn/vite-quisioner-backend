<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExportTask extends Model
{
    use HasUuids;

    protected $table = 'export_tasks';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'filters',
        'file_disk',
        'file_path',
        'error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
