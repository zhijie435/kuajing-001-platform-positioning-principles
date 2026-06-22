<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'real_name',
        'email',
        'phone',
        'role',
        'status',
        'license_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'license_id' => 'integer',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
