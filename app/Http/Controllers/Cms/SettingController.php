<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\UploadMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\PreviewFooterVideoRequest;
use App\Http\Requests\Cms\UpdateSiteSettingsRequest;
use App\Http\Requests\Cms\UploadSiteSettingImageRequest;
use App\Models\Media;
use App\Models\WebsiteSetting;
use App\Services\AuditArchiveAutomationService;
use App\Services\AuditService;
use App\Services\CmsAccess;
use App\Services\SiteSettings;
use App\Support\SiteSettingsCatalog;
use App\Support\SocialLinkNormalizer;
use App\Support\VideoEmbed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class SettingController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private AuditArchiveAutomationService $automation,
    ) {}

    private function authorize(string $permission): void
    {
        abort_unless(auth()->check() && app(CmsAccess::class)->allows(auth()->user(), $permission), 403);
    }

    public function index(string $locale): Response
    {
        $this->authorize('settings.view');

        $settings = WebsiteSetting::query()
            ->with('translations')
            ->get()
            ->keyBy('key');
        $locales = (array) config('website.locales', ['ar', 'en']);

        $rows = collect(SiteSettingsCatalog::all())
            ->map(function (array $meta, string $key) use ($settings, $locales, $locale): array {
                $setting = $settings->get($key);
                $values = [];

                if ($meta['localized']) {
                    foreach ($locales as $code) {
                        $values[$code] = $setting?->translations->firstWhere('locale', $code)?->value;
                    }
                }

                $translationKey = Str::replace(['.'], '_', $key);
                $descriptionKey = 'website.cms.setting_descriptions.'.$translationKey;

                return [
                    'key' => $key,
                    'group' => $meta['group'],
                    'type' => $meta['type'],
                    'localized' => $meta['localized'],
                    'public' => (bool) ($meta['public'] ?? true),
                    'label' => __('website.cms.setting_labels'.'.'.$translationKey, [], $locale),
                    'description' => Lang::has($descriptionKey, $locale) ? __($descriptionKey, [], $locale) : '',
                    'value' => $setting?->value,
                    'translations' => $values,
                ];
            })->values()->all();

        $groups = collect(SiteSettingsCatalog::groupOrder())
            ->map(function (string $key) use ($locale): array {
                $labelKey = 'website.cms.setting_groups.'.$key;
                $descriptionKey = 'website.cms.setting_group_descriptions.'.$key;

                return [
                    'key' => $key,
                    'label' => Lang::has($labelKey, $locale) ? __($labelKey, [], $locale) : Str::headline($key),
                    'description' => Lang::has($descriptionKey, $locale) ? __($descriptionKey, [], $locale) : '',
                ];
            })->values()->all();

        return Inertia::render('Cms/Settings/Index', [
            'settings' => $rows,
            'groups' => $groups,
            'locales' => $locales,
            'canUpdate' => app(CmsAccess::class)->allows(auth()->user(), 'settings.update'),
            'uploadKeys' => SiteSettingsCatalog::imageKeys(),
            'media' => $this->mediaOptions(),
            'timezones' => timezone_identifiers_list(),
            'automation' => $this->automation->status(),
        ]);
    }

    public function update(string $locale, UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $this->authorize('settings.update');

        /** @var array{settings: list<array{key: string, value?: ?string, translations?: array<string, ?string>}>} $validated */
        $validated = $request->validated();
        $before = [];
        $after = [];

        DB::transaction(function () use ($validated, &$before, &$after): void {
            foreach ($validated['settings'] as $entry) {
                $key = $entry['key'];
                $meta = SiteSettingsCatalog::find($key);
                abort_unless($meta !== null, 422);

                $setting = WebsiteSetting::query()->firstOrNew(['key' => $key]);
                $before[$key] = $setting->exists ? $setting->resolvedValue() : null;

                $attributes = [
                    'group' => $meta['group'],
                    'type' => $meta['type'],
                    'is_public' => (bool) ($meta['public'] ?? true),
                    'is_localized' => $meta['localized'],
                ];

                if ($meta['localized']) {
                    $attributes['value'] = null;
                    $setting->fill($attributes)->save();

                    foreach ((array) ($entry['translations'] ?? []) as $code => $value) {
                        if (! in_array($code, (array) config('website.locales', ['ar', 'en']), true)) {
                            continue;
                        }

                        $setting->translations()->updateOrCreate(
                            ['locale' => $code],
                            ['value' => $this->normalize($value)],
                        );
                    }
                } else {
                    $attributes['value'] = $this->normalizeValue($key, $entry['value'] ?? null);
                    $setting->fill($attributes)->save();
                }

                $after[$key] = $setting->fresh()?->resolvedValue();
            }
        });

        app(SiteSettings::class)->flush();
        $this->audit->record('cms.settings.update', 'website_settings', null, $before, $after, [
            'keys' => array_column($validated['settings'], 'key'),
            'description' => 'Website settings updated',
        ]);

        return back()->with('flash', ['success' => __('website.cms.saved')]);
    }

    public function previewVideo(string $locale, PreviewFooterVideoRequest $request): JsonResponse
    {
        $this->authorize('settings.update');

        /** @var array{provider: string, url: string} $validated */
        $validated = $request->validated();
        $resolved = VideoEmbed::resolve($validated['provider'], $validated['url']);

        abort_if($resolved === null, 422, __('website.cms.setting_video_url_invalid'));

        return response()->json($resolved);
    }

    public function uploadImage(
        string $locale,
        UploadSiteSettingImageRequest $request,
        UploadMedia $upload,
    ): RedirectResponse {
        $this->authorize('settings.update');

        /** @var array{key: string} $validated */
        $validated = $request->validated();
        $key = $validated['key'];
        $meta = SiteSettingsCatalog::find($key);
        abort_unless($meta !== null && in_array($key, SiteSettingsCatalog::imageKeys(), true), 422);

        try {
            $media = $upload->handle($request->file('file'), false, $request->user());
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        $setting = WebsiteSetting::query()->firstOrNew(['key' => $key]);
        $before = $setting->exists ? $setting->resolvedValue() : null;

        $setting->fill([
            'group' => $meta['group'],
            'key' => $key,
            'type' => $meta['type'],
            'is_public' => true,
            'is_localized' => false,
            'value' => $media->url(),
        ])->save();

        app(SiteSettings::class)->flush();
        $this->audit->record('cms.settings.image_uploaded', 'website_setting', $key, $before, $media->url(), [
            'media_id' => $media->id,
            'key' => $key,
        ]);

        return back()->with('flash', ['success' => __('website.cms.setting_image_uploaded')]);
    }

    /** @return list<array{id: int, name: string, url: string, type: string, alt: string, restricted: bool}> */
    private function mediaOptions(): array
    {
        if (! app(CmsAccess::class)->allows(auth()->user(), 'media.view')) {
            return [];
        }

        return Media::query()
            ->with('translations')
            ->images()
            ->public()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->original_name,
                'url' => $media->url(),
                'type' => $media->type->value,
                'alt' => $media->t('alt_text', app()->getLocale()),
                'restricted' => false,
            ])
            ->all();
    }

    private function normalize(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeValue(string $key, ?string $value): ?string
    {
        $meta = SiteSettingsCatalog::find($key);
        $type = $meta['type'] ?? 'text';

        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        if ($type === 'integer' || $type === 'interval') {
            return $value === null || $value === '' ? null : (string) (int) $value;
        }

        $value = $this->normalize($value);

        if ($value === null || ! str_starts_with($key, 'social.')) {
            return $value;
        }

        return app(SocialLinkNormalizer::class)->normalize($key, $value);
    }
}
