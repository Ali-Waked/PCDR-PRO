<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UploadAvatar
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 2048 * 1024;

    private const MAX_DIMENSION = 4096;

    public function handle(UploadedFile $file, User $user): Media
    {
        $realPath = (string) $file->getRealPath();
        if ($realPath === '' || ! is_file($realPath)) {
            throw new InvalidArgumentException((string) __('ui.account_profile.avatar_invalid'));
        }

        $detected = $this->detectMimeType($realPath);
        if (! isset(self::MIME_EXTENSIONS[$detected])) {
            throw new InvalidArgumentException((string) __('ui.account_profile.avatar_invalid'));
        }

        $dimensions = @getimagesize($realPath);
        if (! is_array($dimensions)) {
            throw new InvalidArgumentException((string) __('ui.account_profile.avatar_invalid'));
        }

        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException((string) __('ui.account_profile.avatar_invalid'));
        }

        $size = (int) $file->getSize();
        if ($size > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException((string) __('ui.account_profile.avatar_too_large'));
        }

        $disk = (string) config('website.uploads.private_disk', 'private');
        $ulid = (string) Str::ulid();
        $extension = self::MIME_EXTENSIONS[$detected];
        $path = 'avatars/'.$user->id."/{$ulid}.{$extension}";

        Storage::disk($disk)->putFileAs(
            dirname($path),
            $file,
            basename($path),
            'private',
        );

        try {
            return Media::create([
                'uuid' => $ulid,
                'disk' => $disk,
                'path' => $path,
                'type' => MediaType::Image,
                'mime_type' => $detected,
                'extension' => $extension,
                'size_bytes' => $size,
                'width' => $width,
                'height' => $height,
                'checksum' => (string) hash_file('sha256', $realPath),
                'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName()),
                'is_restricted' => true,
                'uploaded_by' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    private function detectMimeType(string $path): string
    {
        $handle = finfo_open(FILEINFO_MIME_TYPE);
        if ($handle === false) {
            return '';
        }

        try {
            return strtolower((string) finfo_file($handle, $path));
        } finally {
            finfo_close($handle);
        }
    }

    private function sanitizeOriginalName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        return $name === '' ? 'avatar' : mb_substr($name, 0, 255);
    }
}
