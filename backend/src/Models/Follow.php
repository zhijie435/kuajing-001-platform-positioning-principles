<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    protected $table = 'follows';

    protected $fillable = [
        'customer_id',
        'user_id',
        'follow_type',
        'content',
        'next_follow_time',
        'license_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'license_id' => 'integer',
        'next_follow_time' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
