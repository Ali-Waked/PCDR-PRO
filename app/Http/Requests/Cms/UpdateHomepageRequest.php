<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateHomepageRequest extends FormRequest
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
            'sections' => ['required', 'array', 'max:30'],
            'sections.*.id' => ['required', 'integer', 'exists:homepage_sections,id'],
            'sections.*.status' => ['sometimes', 'string', 'in:'.implode(',', ContentStatus::values())],
            'sections.*.published_at' => ['nullable', 'date'],
            'sections.*.publishedAt' => ['nullable', 'date'],
            'sections.*.is_enabled' => ['sometimes', 'boolean'],
            'sections.*.display_order' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'sections.*.translations' => ['sometimes', 'array', 'min:1'],
            'sections.*.translations.*' => ['array'],
            'sections.*.translations.*.eyebrow' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.translations.*.body' => ['nullable', 'string', 'max:5000'],
            'sections.*.translations.*.cta_label' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.cta_link' => ['nullable', 'string', 'max:255'],
            'sections.*.translations.*.ticker_label' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.tickerLabel' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.tab_labels' => ['nullable', 'array'],
            'sections.*.translations.*.tabLabels' => ['nullable', 'array'],
            'sections.*.translations.*.tab_labels.*' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.tabLabels.*' => ['nullable', 'string', 'max:120'],
            'sections.*.translations.*.video_url' => ['nullable', 'string', 'max:255'],
            'sections.*.translations.*.videoUrl' => ['nullable', 'string', 'max:255'],
            'sections.*.translations.*.video_caption' => ['nullable', 'string', 'max:500'],
            'sections.*.translations.*.videoCaption' => ['nullable', 'string', 'max:500'],
            'sections.*.items' => ['sometimes', 'array', 'max:24'],
            'sections.*.items.*.id' => ['nullable', 'integer'],
            'sections.*.items.*.remove' => ['sometimes', 'boolean'],
            'sections.*.items.*.item_type' => ['required', 'in:pillar,article,publication,page,project,inline'],
            'sections.*.items.*.item_id' => ['nullable', 'integer'],
            'sections.*.items.*.item_key' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.tab' => ['nullable', 'string', 'max:32'],
            'sections.*.items.*.featured' => ['sometimes', 'boolean'],
            'sections.*.items.*.is_featured' => ['sometimes', 'boolean'],
            'sections.*.items.*.motif_key' => ['nullable', 'string', 'max:32'],
            'sections.*.items.*.motifKey' => ['nullable', 'string', 'max:32'],
            'sections.*.items.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.items.*.is_published' => ['sometimes', 'boolean'],
            'sections.*.items.*.numeric_value' => ['nullable', 'numeric'],
            'sections.*.items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sections.*.items.*.translations' => ['sometimes', 'array', 'min:1'],
            'sections.*.items.*.translations.*' => ['array'],
            'sections.*.items.*.translations.*.label' => ['nullable', 'string', 'max:120'],
            'sections.*.items.*.translations.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.items.*.translations.*.body' => ['nullable', 'string', 'max:5000'],
            'sections.*.items.*.translations.*.display_value' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.translations.*.display' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.translations.*.url' => ['nullable', 'string', 'max:255'],
            'sections.*.items.*.translations.*.date_label' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.translations.*.dateLabel' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.translations.*.cta_label' => ['nullable', 'string', 'max:120'],
            'sections.*.items.*.translations.*.ctaLabel' => ['nullable', 'string', 'max:120'],
            'sections.*.items.*.translations.*.caption' => ['nullable', 'string', 'max:500'],
            'sections.*.items.*.translations.*.unit' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  Validator<mixed>  $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sections = $this->input('sections', []);
            if (! is_array($sections)) {
                return;
            }

            foreach ($sections as $sectionIndex => $section) {
                if (! is_array($section)) {
                    continue;
                }

                $this->validateSectionLinks($validator, $section, (int) $sectionIndex);

                $keys = [];
                $items = $section['items'] ?? [];
                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $itemIndex => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $path = "sections.{$sectionIndex}.items.{$itemIndex}";
                    $itemType = (string) ($item['item_type'] ?? '');
                    $isRemoving = (bool) ($item['remove'] ?? false);

                    if ($isRemoving) {
                        if (empty($item['id'])) {
                            $validator->errors()->add($path.'.id', 'An item ID is required to remove an item.');
                        }

                        continue;
                    }

                    if ($itemType !== 'inline' && empty($item['item_id'])) {
                        $validator->errors()->add($path.'.item_id', 'An item ID is required for this item type.');
                    }

                    if (! empty($item['item_key'])) {
                        $itemKey = (string) $item['item_key'];
                        if (in_array($itemKey, $keys, true)) {
                            $validator->errors()->add($path.'.item_key', 'Item keys must be unique within a section.');
                        }
                        $keys[] = $itemKey;
                    }

                    $this->validateItemMedia($validator, $item, $path);
                    $this->validateItemLinks($validator, $item, $path);
                }
            }
        });
    }

    /**
     * @param  Validator<mixed>  $validator
     * @param  array<string, mixed>  $section
     */
    private function validateSectionLinks(Validator $validator, array $section, int $sectionIndex): void
    {
        $translations = $section['translations'] ?? [];
        if (! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach (['cta_link', 'video_url', 'videoUrl'] as $field) {
                if (! $this->isSafeLink($values[$field] ?? null)) {
                    $validator->errors()->add(
                        "sections.{$sectionIndex}.translations.{$locale}.{$field}",
                        'Links must be an internal path, an anchor, or an https:// URL.',
                    );
                }
            }
        }
    }

    /**
     * @param  Validator<mixed>  $validator
     * @param  array<string, mixed>  $item
     */
    private function validateItemLinks(Validator $validator, array $item, string $path): void
    {
        $translations = $item['translations'] ?? [];
        if (! is_array($translations)) {
            return;
        }

        foreach ($translations as $locale => $values) {
            if (is_array($values) && ! $this->isSafeLink($values['url'] ?? null)) {
                $validator->errors()->add(
                    "{$path}.translations.{$locale}.url",
                    'Links must be an internal path, an anchor, or an https:// URL.',
                );
            }
        }
    }

    /**
     * @param  Validator<mixed>  $validator
     * @param  array<string, mixed>  $item
     */
    private function validateItemMedia(Validator $validator, array $item, string $path): void
    {
        $mediaId = $item['media_id'] ?? null;
        if ($mediaId === null || $mediaId === '') {
            return;
        }

        $exists = Media::query()
            ->images()
            ->public()
            ->whereKey((int) $mediaId)
            ->exists();

        if (! $exists) {
            $validator->errors()->add($path.'.media_id', 'Select a public image from the media library.');
        }
    }

    /**
     * Only anchors, single-slash internal paths and https URLs are accepted.
     * Backslashes, encoded backslashes, whitespace and control characters are
     * rejected so browsers cannot normalize a value into a scheme-relative URL.
     */
    private function isSafeLink(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (! is_string($value) || $value !== trim($value)) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
            return false;
        }

        if (str_contains($value, '\\') || stripos($value, '%5c') !== false) {
            return false;
        }

        if (preg_match('/^#[A-Za-z0-9_:-]+$/', $value) === 1) {
            return true;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        return preg_match('/^https:\/\/[^\s]+$/i', $value) === 1;
    }
}
