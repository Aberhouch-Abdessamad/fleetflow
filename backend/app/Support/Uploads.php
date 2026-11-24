<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tenant files live on the PRIVATE disk under companies/{company_id}/… with random
 * names. They are only ever served through authorised download endpoints.
 */
final class Uploads
{
    public const DISK = 'local';

    public const IMAGE_RULES = ['file', 'mimes:jpg,jpeg,png,webp', 'max:8192'];

    public const DOCUMENT_RULES = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

    public static function store(UploadedFile $file, int $companyId, string $folder): string
    {
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');

        return $file->storeAs("companies/{$companyId}/{$folder}", Str::random(40).'.'.$ext, self::DISK);
    }

    /** Store a base64 data-URL (signature pad). */
    public static function storeDataUrl(string $dataUrl, int $companyId, string $folder): string
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#', $dataUrl, $m)) {
            throw new \InvalidArgumentException('Invalid image data.');
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Invalid or too large image.');
        }
        $path = "companies/{$companyId}/{$folder}/".Str::random(40).'.'.($m[1] === 'jpeg' ? 'jpg' : $m[1]);
        Storage::disk(self::DISK)->put($path, $bytes);

        return $path;
    }

    public static function belongsTo(string $path, int $companyId): bool
    {
        return str_starts_with($path, "companies/{$companyId}/") && ! str_contains($path, '..');
    }

    public static function download(?string $path, int $companyId, ?string $name = null)
    {
        abort_unless($path && self::belongsTo($path, $companyId) && Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path, $name, [
            'Cache-Control' => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
