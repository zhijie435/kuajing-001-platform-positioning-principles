<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RedLineConfig extends Model
{
    protected $table = 'redline_configs';

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'platform',
    ];

    protected $casts = [
        'config_value' => 'json',
    ];

    public static function getAllByPlatform(string $platform): array
    {
        return self::where('platform', $platform)
            ->get()
            ->pluck('config_value', 'config_key')
            ->toArray();
    }

    public static function getValue(string $key, string $platform = 'all')
    {
        $config = self::where('config_key', $key)
            ->where('platform', $platform)
            ->first();

        return $config ? $config->config_value : null;
    }
}
