<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Services\CmsAccess;
use App\Support\SiteSettingsCatalog;
use App\Support\SocialLinkNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(CmsAccess::class)->allows($user, 'settings.update');
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');
        if (! is_array($settings)) {
            return;
        }

        $this->merge([
            'settings' => array_map(function (mixed $entry): mixed {
                if (! is_array($entry)) {
                    return $entry;
                }

                $key = $entry['key'] ?? null;
                $meta = SiteSettingsCatalog::find(is_string($key) ? $key : null);

                if ($meta !== null && $meta['type'] === 'boolean') {
                    $entry['value'] = filter_var($entry['value'] ?? null, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                }

                return $entry;
            }, $settings),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $locales = (array) config('website.locales', ['ar', 'en']);

        $rules = [
            'settings' => ['required', 'array', 'max:'.count(SiteSettingsCatalog::all())],
            'settings.*.key' => ['required', 'string', 'distinct', Rule::in(SiteSettingsCatalog::keys())],
            'settings.*.value' => ['nullable', 'string', 'max:2000'],
            'settings.*.translations' => ['nullable', 'array'],
            'settings.*.translations.*' => ['nullable', 'string', 'max:2000'],
        ];

        $submitted = $this->input('settings');

        if (! is_array($submitted)) {
            return $rules;
        }

        foreach ($submitted as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;
            $meta = SiteSettingsCatalog::find(is_string($key) ? $key : null);

            if ($meta === null) {
                continue;
            }

            $prefix = 'settings.'.$index;

            if (str_starts_with($key, 'social.')) {
                $rules["{$prefix}.value"] = ['nullable', 'string', 'max:2000', $this->socialUrlRule($key)];

                continue;
            }

            if ($meta['localized']) {
                $translations = $entry['translations'] ?? [];

                foreach ($locales as $locale) {
                    if (is_array($translations) && array_key_exists($locale, $translations)) {
                        $rules["{$prefix}.translations.{$locale}"] = $this->typeRules($meta['type']);
                    }
                }

                continue;
            }

            $rules["{$prefix}.value"] = $this->typeRules($meta['type']);

            if ($key === 'audit_automation.lookback_months') {
                $rules["{$prefix}.value"] = ['nullable', 'integer', 'min:1', 'max:12'];
            }
        }

        return $rules;
    }

    /** @return list<mixed> */
    private function typeRules(string $type): array
    {
        return match ($type) {
            'email' => ['nullable', 'string', 'max:2000', 'email:rfc'],
            'url' => ['nullable', 'string', 'max:2000', $this->publicUrlRule()],
            'locale' => ['nullable', Rule::in((array) config('website.locales', ['ar', 'en']))],
            'boolean' => ['nullable', Rule::in(['0', '1', 'true', 'false', true, false])],
            'interval' => ['nullable', Rule::in(['1', '2', '3', 1, 2, 3])],
            'integer' => ['nullable', 'integer', 'min:1', 'max:28'],
            'time' => ['nullable', 'string', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            default => ['nullable', 'string', 'max:2000'],
        };
    }

    private function socialUrlRule(string $key): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($key): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || app(SocialLinkNormalizer::class)->normalize($key, $value) === null) {
                $fail(__('website.cms.setting_social_invalid'));
            }
        };
    }

    private function publicUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value)) {
                $fail(__('website.cms.setting_url_invalid'));

                return;
            }

            if (str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\')) {
                return;
            }

            $parts = parse_url($value);
            $scheme = is_array($parts) && isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
            $host = is_array($parts) && isset($parts['host']) ? (string) $parts['host'] : '';

            if (in_array($scheme, ['http', 'https'], true) && $host !== '') {
                return;
            }

            $fail(__('website.cms.setting_url_invalid'));
        };
    }
}
