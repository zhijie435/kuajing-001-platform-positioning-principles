<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'company',
        'level',
        'source',
        'status',
        'follow_status',
        'next_follow_time',
        'assigned_user_id',
        'license_id',
        'remark',
    ];

    protected $casts = [
        'next_follow_time' => 'datetime',
        'assigned_user_id' => 'integer',
        'license_id' => 'integer',
    ];

    public function follows()
    {
        return $this->hasMany(Follow::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
