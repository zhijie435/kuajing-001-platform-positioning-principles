<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'username',
        'action',
        'module',
        'platform',
        'ip',
        'user_agent',
        'request_method',
        'request_path',
        'request_params',
        'response_code',
        'guard_result',
        'status',
        'remark',
    ];

    protected $casts = [
        'request_params' => 'json',
    ];

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
