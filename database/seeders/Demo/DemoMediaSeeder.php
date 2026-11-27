<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generic stock imagery for the preview media library.
 *
 * Captions and alt text describe only the visible subject; they never claim to
 * document real activities. Source files are committed locally and copied to
 * deterministic public-disk paths so the generated files can be cleaned up.
 */
final class DemoMediaSeeder extends Seeder
{
    private const OWNER_KEY = 'owner';

    private const AVATAR_USER_KEY = 'editor';

    private const AVATAR_USER_EMAIL = 'hana.rafiq@pcdr.test';

    private const AVATAR_MEDIA_SLUG = 'classroom';

    /** @var array<string, array{ar: array{alt_text: string, caption: string}, en: array{alt_text: string, caption: string}}> */
    private const IMAGES = [
        'adult-education' => [
            'ar' => [
                'alt_text' => 'جلسة تعليمية للبالغين في قاعة دراسية',
                'caption' => 'صورة توضيحية لجلسة تعليمية للبالغين تجمع متعلمين حول طاولة دراسية.',
            ],
            'en' => [
                'alt_text' => 'Adult learners in a classroom training session',
                'caption' => 'Illustrative image of an adult education session around a shared table.',
            ],
        ],
        'classroom' => [
            'ar' => [
                'alt_text' => 'قاعة دراسية مجهزة بسبورة وطاولات',
                'caption' => 'صورة توضيحية لقاعة دراسية بسيطة تصلح لورش العمل والدروس.',
            ],
            'en' => [
                'alt_text' => 'Classroom with a board and writing tables',
                'caption' => 'Illustrative image of a simple classroom used for workshops and lessons.',
            ],
        ],
        'community-garden' => [
            'ar' => [
                'alt_text' => 'حديقة مجتمعية بأحواض نباتات خضراء',
                'caption' => 'صورة توضيحية لحديقة مشتركة يعتني بها أفراد من الحي.',
            ],
            'en' => [
                'alt_text' => 'Community garden with green planting beds',
                'caption' => 'Illustrative image of a shared garden tended by neighbourhood members.',
            ],
        ],
        'donation-sorting' => [
            'ar' => [
                'alt_text' => 'فرز مواد تبرعات داخل صناديق',
                'caption' => 'صورة توضيحية لطاولة تُفرز عليها مواد تبرعات قبل توزيعها.',
            ],
            'en' => [
                'alt_text' => 'Donated supplies being sorted into boxes',
                'caption' => 'Illustrative image of donated goods being sorted on a table.',
            ],
        ],
        'farmer-harvest' => [
            'ar' => [
                'alt_text' => 'مزارع يحصد محصولاً في حقل',
                'caption' => 'صورة توضيحية لمزارع يجمع محصولاً موسمياً في أرض زراعية.',
            ],
            'en' => [
                'alt_text' => 'Farmer gathering a crop in a field',
                'caption' => 'Illustrative image of a farmer collecting a seasonal crop in a field.',
            ],
        ],
        'food-donations' => [
            'ar' => [
                'alt_text' => 'صناديق مواد غذائية جاهزة للتبرع',
                'caption' => 'صورة توضيحية لصناديق مواد غذائية مرتبة تمهيداً لتسليمها.',
            ],
            'en' => [
                'alt_text' => 'Boxes of food supplies prepared for donation',
                'caption' => 'Illustrative image of food boxes arranged before handover.',
            ],
        ],
        'food-handover' => [
            'ar' => [
                'alt_text' => 'تسليم صندوق مواد غذائية بين شخصين',
                'caption' => 'صورة توضيحية لعملية تسليم بسيطة لمواد غذائية بين شخصين.',
            ],
            'en' => [
                'alt_text' => 'Food parcel handed from one person to another',
                'caption' => 'Illustrative image of a food parcel being handed over between two people.',
            ],
        ],
        'food-packing' => [
            'ar' => [
                'alt_text' => 'تعبئة مواد غذائية في أكياس وصناديق',
                'caption' => 'صورة توضيحية لطاولة تعبئة تُجهز فيها حزم غذائية للاستخدام المجتمعي.',
            ],
            'en' => [
                'alt_text' => 'Food supplies being packed into bags and boxes',
                'caption' => 'Illustrative image of a packing table preparing food parcels.',
            ],
        ],
        'handwashing' => [
            'ar' => [
                'alt_text' => 'غسل اليدين بماء وصابون',
                'caption' => 'صورة توضيحية لخطوة غسل اليدين كجزء من ممارسات النظافة اليومية.',
            ],
            'en' => [
                'alt_text' => 'Hands being washed with water and soap',
                'caption' => 'Illustrative image of handwashing as part of everyday hygiene practice.',
            ],
        ],
        'health-check' => [
            'ar' => [
                'alt_text' => 'فحص صحي روتيني باستخدام جهاز بسيط',
                'caption' => 'صورة توضيحية لفحص صحي روتيني تجريه ممرضة في عيادة محلية.',
            ],
            'en' => [
                'alt_text' => 'Routine health check with a simple device',
                'caption' => 'Illustrative image of a routine health check at a local clinic.',
            ],
        ],
        'lecture' => [
            'ar' => [
                'alt_text' => 'محاضرة في قاعة مكتظة بالمستمعين',
                'caption' => 'صورة توضيحية لمحاضرة تعليمية يلقيها متحدث أمام مجموعة من المستمعين.',
            ],
            'en' => [
                'alt_text' => 'Lecture delivered to an audience in a hall',
                'caption' => 'Illustrative image of an educational lecture given to a group of listeners.',
            ],
        ],
        'olive-farming' => [
            'ar' => [
                'alt_text' => 'أشجار زيتون في أرض زراعية',
                'caption' => 'صورة توضيحية لزراعة الزيتون وقطف ثماره في موسم الحصاد.',
            ],
            'en' => [
                'alt_text' => 'Olive trees on cultivated land',
                'caption' => 'Illustrative image of olive cultivation and fruit picking at harvest time.',
            ],
        ],
        'planting' => [
            'ar' => [
                'alt_text' => 'زراعة شتلة صغيرة في التراب',
                'caption' => 'صورة توضيحية لشتلة تُزرع بعناية في حوض أو حقل.',
            ],
            'en' => [
                'alt_text' => 'Planting a young seedling in soil',
                'caption' => 'Illustrative image of a young seedling being planted carefully in soil.',
            ],
        ],
        'soap' => [
            'ar' => [
                'alt_text' => 'قطع صابون ومنتجات تنظيف',
                'caption' => 'صورة توضيحية لقطع صابون ومنتجات تنظيف مرتبة على طاولة.',
            ],
            'en' => [
                'alt_text' => 'Bars of soap and cleaning products',
                'caption' => 'Illustrative image of soap bars and cleaning items arranged on a table.',
            ],
        ],
        'tree-planting' => [
            'ar' => [
                'alt_text' => 'زراعة شجرة صغيرة بمعاونة مجموعة',
                'caption' => 'صورة توضيحية لمجموعة تشارك في زراعة شجرة صغيرة.',
            ],
            'en' => [
                'alt_text' => 'Planting a young tree together',
                'caption' => 'Illustrative image of a group planting a young tree.',
            ],
        ],
        'vegetable-harvest' => [
            'ar' => [
                'alt_text' => 'حصاد خضار طازجة من حقل',
                'caption' => 'صورة توضيحية لحصاد محصول خضار موسمي في أرض زراعية.',
            ],
            'en' => [
                'alt_text' => 'Fresh vegetables gathered at harvest',
                'caption' => 'Illustrative image of a seasonal vegetable harvest in a field.',
            ],
        ],
        'water-well' => [
            'ar' => [
                'alt_text' => 'بئر ماء محاط بمسار للاستخدام اليومي',
                'caption' => 'صورة توضيحية لنقطة ماء محلية يمكن الوصول إليها للاستخدام اليومي.',
            ],
            'en' => [
                'alt_text' => 'Water point surrounded by a daily-use path',
                'caption' => 'Illustrative image of a local water point used for everyday needs.',
            ],
        ],
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        $disk = (string) config('website.uploads.public_disk', 'public');
        $ownerId = $this->resolveOwnerId($manifest);
        $avatarMediaId = null;

        foreach (self::IMAGES as $slug => $copy) {
            $mediaId = $this->seedImage($manifest, $slug, $disk, $ownerId, $copy);

            if ($slug === self::AVATAR_MEDIA_SLUG) {
                $avatarMediaId = $mediaId;
            }
        }

        $this->assignAvatar($manifest, $avatarMediaId);
    }

    /**
     * @param  array{ar: array{alt_text: string, caption: string}, en: array{alt_text: string, caption: string}}  $copy
     */
    private function seedImage(
        DemoSeedManifest $manifest,
        string $slug,
        string $disk,
        int|string|null $ownerId,
        array $copy,
    ): int|string {
        $sourcePath = storage_path('app/public/media/demo/images/'.$slug.'.jpg');

        if (! is_readable($sourcePath)) {
            throw new RuntimeException("Preview image [{$slug}] is not readable.");
        }

        $dimensions = @getimagesize($sourcePath);

        if (! is_array($dimensions) || ($dimensions['mime'] ?? null) !== 'image/jpeg') {
            throw new RuntimeException("Preview image [{$slug}] is not a readable JPEG.");
        }

        $bytes = file_get_contents($sourcePath);

        if ($bytes === false) {
            throw new RuntimeException("Preview image [{$slug}] could not be read.");
        }

        $size = strlen($bytes);
        $checksum = hash('sha256', $bytes);
        $path = 'media/2026/11/stock-'.$slug.'.jpg';

        if (Storage::disk($disk)->put($path, $bytes, 'public') === false) {
            throw new RuntimeException("Preview image [{$slug}] could not be written to disk [{$disk}].");
        }

        $ulid = DemoSeedManifest::fixedUlid('media:'.$slug);

        $mediaId = $manifest->ensureRow(
            'media',
            'media',
            $slug,
            [
                'uuid' => $ulid,
                'disk' => $disk,
                'path' => $path,
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'extension' => 'jpg',
                'size_bytes' => $size,
                'width' => (int) ($dimensions[0] ?? 0),
                'height' => (int) ($dimensions[1] ?? 0),
                'checksum' => $checksum,
                'original_name' => $slug.'.jpg',
                'is_restricted' => false,
                'uploaded_by' => $ownerId,
                ...DemoSeedManifest::stamps(),
            ],
            ['uuid' => $ulid],
        );

        foreach ($copy as $locale => $text) {
            $manifest->ensureRow(
                'media_translations',
                'media_translations',
                $slug.':'.$locale,
                [
                    'media_id' => $mediaId,
                    'locale' => $locale,
                    'alt_text' => $text['alt_text'],
                    'caption' => $text['caption'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['media_id' => $mediaId, 'locale' => $locale],
            );
        }

        $manifest->trackFile($slug, $disk, $path, [
            'checksum' => $checksum,
            'size_bytes' => $size,
            'natural_key' => $slug,
        ]);

        return $mediaId;
    }

    private function resolveOwnerId(DemoSeedManifest $manifest): int|string|null
    {
        $trackedId = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.self::OWNER_KEY);

        if ($trackedId !== null && $trackedId !== '') {
            return ctype_digit($trackedId) ? (int) $trackedId : $trackedId;
        }

        $ownerId = DB::table('users')->where('is_platform_owner', true)->orderBy('id')->value('id');

        return is_int($ownerId) || is_string($ownerId) ? $ownerId : null;
    }

    private function assignAvatar(DemoSeedManifest $manifest, int|string|null $mediaId): void
    {
        if ($mediaId === null) {
            return;
        }

        $trackedUserId = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.self::AVATAR_USER_KEY);

        if ($trackedUserId === null || $trackedUserId === '') {
            return;
        }

        DB::table('users')
            ->where('id', $trackedUserId)
            ->where('email', self::AVATAR_USER_EMAIL)
            ->update([
                'avatar_media_id' => $mediaId,
                'updated_at' => DemoSeedManifest::BASE_TIME,
            ]);
    }
}
