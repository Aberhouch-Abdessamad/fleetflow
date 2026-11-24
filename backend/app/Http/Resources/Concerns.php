<?php

namespace App\Http\Resources;

final class Concerns
{
    public static function file(?string $path, string $kind, int $id): ?string
    {
        return $path ? "/api/v1/files/{$kind}/{$id}" : null;
    }

    public static function date($v): ?string
    {
        return $v?->toDateString();
    }

    public static function dt($v): ?string
    {
        return $v?->toIso8601String();
    }
}
