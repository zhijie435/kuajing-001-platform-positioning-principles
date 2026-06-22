<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $table = 'licenses';

    protected $fillable = [
        'license_key',
        'license_type',
        'platform',
        'max_users',
        'max_customers',
        'max_follows_per_day',
        'status',
        'expired_at',
        'activated_at',
        'company_name',
        'contact_email',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'activated_at' => 'datetime',
        'max_users' => 'integer',
        'max_customers' => 'integer',
        'max_follows_per_day' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expired_at === null || $this->expired_at->isFuture());
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('license_key', $key);
    }
}
