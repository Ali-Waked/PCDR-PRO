<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UploadAvatarRequest;
use App\Models\Media;
use App\Models\User;
use App\Services\AccountAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class AvatarController extends Controller
{
    public function __construct(private readonly AccountAvatarService $avatars) {}

    public function store(string $locale, UploadAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $file = $request->file('avatar');
        abort_if(! $file instanceof UploadedFile, 422);

        $this->avatars->store($user, $file);

        return back()->with('success', __('ui.account_profile.avatar_uploaded'));
    }

    public function destroy(string $locale, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $this->avatars->destroy($user);

        return back()->with('success', __('ui.account_profile.avatar_removed'));
    }

    public function download(string $locale, Media $media, Request $request): Response
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 403);

        $allowed = (int) $media->uploaded_by === (int) $user->id
            || $user->avatar_media_id === $media->id;
        abort_if(! $allowed, 403);

        $path = (string) $media->path;
        abort_if(! preg_match('#^[A-Za-z0-9._/-]+$#', $path) || str_contains($path, '..'), 403);

        return Storage::disk($media->disk)->response(
            $path,
            'avatar.'.$media->extension,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
