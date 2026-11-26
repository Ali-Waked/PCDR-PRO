<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Services\CmsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDirectoryPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $access = app(CmsAccess::class);

        if ($user === null || ! $access->allows($user, 'directory_partners.create')) {
            return false;
        }

        if ($this->string('status')->toString() === ContentStatus::Published->value
            && ! $access->allows($user, 'directory_partners.publish')) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'directory_partner_category_id' => ['nullable', 'integer', 'exists:directory_partner_categories,id'],
            'logo_media_id' => [
                'nullable', 'integer',
                Rule::exists('media', 'id')->where(static fn ($query) => $query
                    ->where('type', MediaType::Image->value)
                    ->where('is_restricted', false)),
            ],
            'website_url' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/[^\s\\\\]+$/i', 'url'],
            'partnership_year' => ['nullable', 'integer', 'between:1900,'.((int) date('Y') + 1)],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.ar.name' => ['required_with:translations.ar', 'string', 'max:180'],
            'translations.en.name' => ['required_with:translations.en', 'string', 'max:180'],
            'translations.ar.slug' => [
                'nullable', 'string', 'max:180', 'regex:/^[^\s\/]+$/u',
                Rule::unique('directory_partner_translations', 'slug')
                    ->where(static fn ($query) => $query->where('locale', 'ar')),
            ],
            'translations.en.slug' => [
                'nullable', 'string', 'max:180', 'regex:/^[^\s\/]+$/u',
                Rule::unique('directory_partner_translations', 'slug')
                    ->where(static fn ($query) => $query->where('locale', 'en')),
            ],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $translations = $this->input('translations', []);
        if (is_array($translations)) {
            foreach ($translations as $locale => $values) {
                if (is_array($values) && isset($values['name'])) {
                    $translations[$locale]['name'] = trim((string) $values['name']);
                }
            }
        }

        $website = $this->input('website_url');
        $this->merge([
            'translations' => $translations,
            'website_url' => is_string($website) && trim($website) !== '' ? trim($website) : null,
        ]);
    }
}
