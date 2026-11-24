<?php

namespace App\Support;

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

final class Platform
{
    public static function setting(string $key): mixed
    {
        $all = Cache::remember('platform_settings', 300, fn () => PlatformSetting::pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : (AdminController::DEFAULT_SETTINGS[$key] ?? null);
    }
}
