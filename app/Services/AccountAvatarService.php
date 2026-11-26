<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\DeleteMedia;
use App\Actions\UploadAvatar;
use App\Exceptions\MediaInUseException;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class AccountAvatarService
{
    public function __construct(
        private readonly UploadAvatar $uploadAvatar,
        private readonly DeleteMedia $deleteMedia,
        private readonly AuditService $audit,
    ) {}

    public function store(User $user, UploadedFile $file): Media
    {
        $previous = $user->avatar()->first();
        $media = $this->uploadAvatar->handle($file, $user);

        DB::transaction(static function () use ($user, $media): void {
            $user->forceFill(['avatar_media_id' => $media->id])->save();
        });

        if ($previous instanceof Media && $previous->id !== $media->id) {
            $this->discard($previous);
        }

        $this->audit->record('account.avatar.uploaded', 'users', (string) $user->id, null, [
            'media_id' => (int) $media->id,
        ]);

        return $media;
    }

    public function destroy(User $user): bool
    {
        $media = $user->avatar()->first();
        if (! $media instanceof Media) {
            return false;
        }

        DB::transaction(static function () use ($user): void {
            $user->forceFill(['avatar_media_id' => null])->save();
        });

        $this->discard($media);

        $this->audit->record('account.avatar.removed', 'users', (string) $user->id, null, [
            'media_id' => (int) $media->id,
        ]);

        return true;
    }

    private function discard(Media $media): void
    {
        try {
            $this->deleteMedia->handle($media);
        } catch (MediaInUseException) {
            // Keep the private file when another record still references it.
        }
    }
}
