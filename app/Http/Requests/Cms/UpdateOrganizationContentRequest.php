<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Models\Media;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateOrganizationContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CmsAccess::class)->allows($user, 'homepage.update');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'destination_url' => ['nullable', 'string', 'max:255'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string', 'max:10000'],
            'translations.*.vision_title' => ['nullable', 'string', 'max:160'],
            'translations.*.vision' => ['nullable', 'string', 'max:5000'],
            'translations.*.mission_title' => ['nullable', 'string', 'max:160'],
            'translations.*.mission' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @param Validator<mixed> $validator */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mediaId = $this->integer('logo_media_id');
            if ($mediaId > 0 && ! Media::query()->images()->public()->whereKey($mediaId)->exists()) {
                $validator->errors()->add('logo_media_id', 'Select a public image from the media library.');
            }

            $url = $this->input('destination_url');
            if (is_string($url) && $url !== '' && ! $this->isSafeLink($url)) {
                $validator->errors()->add('destination_url', 'Links must be an internal path, an anchor, or an https:// URL.');
            }
        });
    }

    private function isSafeLink(string $value): bool
    {
        if ($value !== trim($value) || preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
            return false;
        }

        if (str_contains($value, '\\') || stripos($value, '%5c') !== false) {
            return false;
        }

        return preg_match('/^#[A-Za-z0-9_:-]+$/', $value) === 1
            || (str_starts_with($value, '/') && ! str_starts_with($value, '//'))
            || preg_match('/^https:\/\/[^\s]+$/i', $value) === 1;
    }
}
