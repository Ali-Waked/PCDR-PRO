<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Models\Article;
use App\Models\DirectoryPartner;
use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\Media;
use App\Models\Program;
use App\Models\Project;
use App\Models\StrategicPillar;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SaveSectionContentRequest extends FormRequest
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
            'item_key' => ['nullable', 'string', 'max:64'],
            'item_type' => ['required', 'string', 'in:inline,article,project,pillar,program,partner'],
            'item_id' => ['nullable', 'integer', 'min:1'],
            'parent_item_id' => ['nullable', 'integer', 'exists:homepage_section_items,id'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'tab' => ['nullable', 'string', 'max:32'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'motif_key' => ['nullable', 'string', 'max:32'],
            'display_start_at' => ['nullable', 'date'],
            'display_end_at' => ['nullable', 'date', 'after_or_equal:display_start_at'],
            'numeric_value' => ['nullable', 'numeric'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*' => ['array'],
            'translations.*.label' => ['nullable', 'string', 'max:2000'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.alt_text' => ['nullable', 'string', 'max:255'],
            'translations.*.body' => ['nullable', 'string', 'max:10000'],
            'translations.*.summary' => ['nullable', 'string', 'max:2000'],
            'translations.*.display_value' => ['nullable', 'string', 'max:64'],
            'translations.*.url' => ['nullable', 'string', 'max:255'],
            'translations.*.date_label' => ['nullable', 'string', 'max:64'],
            'translations.*.cta_label' => ['nullable', 'string', 'max:120'],
            'translations.*.caption' => ['nullable', 'string', 'max:500'],
            'translations.*.unit' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @param Validator<mixed> $validator */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sectionKey = (string) $this->route('section');
            $definition = config('cms_structure.sections.'.$sectionKey);
            if (! is_array($definition)) {
                $validator->errors()->add('section', 'Unknown predefined section.');

                return;
            }

            $itemType = (string) $this->input('item_type');
            $expectedSource = (string) ($definition['source'] ?? '');
            $contentType = (string) ($definition['content'] ?? '');

            if ($contentType === 'links' && $itemType !== $expectedSource) {
                $validator->errors()->add('item_type', 'This section accepts only '.$expectedSource.' records.');
            }

            if ($contentType !== 'links' && $itemType !== 'inline') {
                $validator->errors()->add('item_type', 'This section accepts inline content only.');
            }

            if ($itemType !== 'inline') {
                $this->validateSourceRecord($validator, $itemType, $this->integer('item_id'));
            }

            $mediaId = $this->integer('media_id');
            if ($mediaId > 0 && ! Media::query()->images()->public()->whereKey($mediaId)->exists()) {
                $validator->errors()->add('media_id', 'Select a public image from the media library.');
            }

            $parentId = $this->integer('parent_item_id');
            if ($parentId > 0) {
                $section = HomepageSection::query()->where('key', $sectionKey)->first();
                $belongs = $section !== null && HomepageSectionItem::query()
                    ->whereKey($parentId)
                    ->where('homepage_section_id', $section->id)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('parent_item_id', 'The parent image must belong to this section.');
                }
            }

            foreach ($this->input('translations', []) as $locale => $values) {
                if (! is_array($values) || ! $this->isSafeLink($values['url'] ?? null)) {
                    $validator->errors()->add("translations.{$locale}.url", 'Links must be an internal path, an anchor, or an https:// URL.');
                }
            }
        });
    }

    /** @param Validator<mixed> $validator */
    private function validateSourceRecord(Validator $validator, string $type, int $id): void
    {
        $model = match ($type) {
            'article' => Article::class,
            'project' => Project::class,
            'pillar' => StrategicPillar::class,
            'program' => Program::class,
            'partner' => DirectoryPartner::class,
            default => null,
        };

        if ($model === null || $id <= 0 || ! $model::query()->whereKey($id)->exists()) {
            $validator->errors()->add('item_id', 'Select an existing content record.');
        }
    }

    private function isSafeLink(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (! is_string($value) || $value !== trim($value) || preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
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
