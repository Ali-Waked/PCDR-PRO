<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use App\Models\Media;
use App\Services\HomepageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Repairs only the seeded hero slides that have no media relationship.
 *
 * Existing media choices are never replaced and no hero record is deleted,
 * merged or duplicated. The images come from the committed demo library and
 * are written to public-disk paths that are safe for unauthenticated styling.
 */
final class HeroMediaRepairSeeder extends Seeder
{
    /** @var array<string, array{slug: string, alt: array{ar: string, en: string}, caption: array{ar: string, en: string}}> */
    private const SLIDES = [
        'slide-1' => [
            'slug' => 'community-garden',
            'alt' => [
                'ar' => 'صورة توضيحية لمجموعة تعمل في حديقة مجتمعية',
                'en' => 'Illustrative image of a group working in a community garden',
            ],
            'caption' => [
                'ar' => 'حديقة مجتمعية - صورة توضيحية',
                'en' => 'Community garden - illustrative image',
            ],
        ],
        'slide-2' => [
            'slug' => 'food-donations',
            'alt' => [
                'ar' => 'صورة توضيحية لمواد غذائية جاهزة للتوزيع',
                'en' => 'Illustrative image of food prepared for distribution',
            ],
            'caption' => [
                'ar' => 'مواد غذائية - صورة توضيحية',
                'en' => 'Food donations - illustrative image',
            ],
        ],
        'slide-3' => [
            'slug' => 'farmer-harvest',
            'alt' => [
                'ar' => 'صورة توضيحية لمزارع أثناء حصاد المحصول',
                'en' => 'Illustrative image of a farmer harvesting crops',
            ],
            'caption' => [
                'ar' => 'حصاد المحصول - صورة توضيحية',
                'en' => 'Farmer harvest - illustrative image',
            ],
        ],
        'slide-4' => [
            'slug' => 'health-check',
            'alt' => [
                'ar' => 'صورة توضيحية لفحص صحي',
                'en' => 'Illustrative image of a health check',
            ],
            'caption' => [
                'ar' => 'فحص صحي - صورة توضيحية',
                'en' => 'Health check - illustrative image',
            ],
        ],
        'slide-5' => [
            'slug' => 'classroom',
            'alt' => [
                'ar' => 'صورة توضيحية لقاعة تعليمية',
                'en' => 'Illustrative image of a classroom',
            ],
            'caption' => [
                'ar' => 'قاعة تعليمية - صورة توضيحية',
                'en' => 'Classroom - illustrative image',
            ],
        ],
    ];

    public function run(): void
    {
        $section = HomepageSection::query()->where('key', 'hero')->first();

        if ($section === null) {
            return;
        }

        foreach (self::SLIDES as $itemKey => $definition) {
            $item = $section->items()->where('item_key', $itemKey)->first();

            if ($item === null || $item->media_id !== null) {
                continue;
            }

            $media = $this->publicImage($itemKey, $definition);
            $item->update(['media_id' => $media->id]);
        }

        app(HomepageService::class)->flush();
    }

    /**
     * @param  array{slug: string, alt: array{ar: string, en: string}, caption: array{ar: string, en: string}}  $definition
     */
    private function publicImage(string $itemKey, array $definition): Media
    {
        $sourcePath = storage_path('app/public/media/demo/images/'.$definition['slug'].'.jpg');

        if (! is_readable($sourcePath)) {
            throw new RuntimeException("Hero demo image [{$definition['slug']}] is not readable.");
        }

        $bytes = file_get_contents($sourcePath);
        $dimensions = @getimagesize($sourcePath);

        if ($bytes === false || ! is_array($dimensions) || ($dimensions['mime'] ?? null) !== 'image/jpeg') {
            throw new RuntimeException("Hero demo image [{$definition['slug']}] is not a readable JPEG.");
        }

        $path = 'media/hero/'.$itemKey.'.jpg';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $disk->put($path, $bytes, 'public');
        }

        $media = Media::withTrashed()->firstOrNew(['path' => $path]);

        if ($media->trashed()) {
            $media->restore();
        }

        if (! $media->exists) {
            $media->fill([
                'uuid' => Str::uuid()->toString(),
                'disk' => 'public',
                'path' => $path,
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size_bytes' => strlen($bytes),
                'width' => (int) ($dimensions[0] ?? 0),
                'height' => (int) ($dimensions[1] ?? 0),
                'checksum' => hash('sha256', $bytes),
                'original_name' => basename($sourcePath),
                'is_restricted' => false,
            ])->save();

            foreach (['ar', 'en'] as $locale) {
                $media->translations()->create([
                    'locale' => $locale,
                    'alt_text' => $definition['alt'][$locale],
                    'caption' => $definition['caption'][$locale],
                ]);
            }
        }

        return $media;
    }
}
