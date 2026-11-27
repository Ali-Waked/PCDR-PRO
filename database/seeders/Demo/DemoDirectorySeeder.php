<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fictional public partner directory for the preview dataset.
 *
 * Names, websites and contacts are invented and use reserved .test domains.
 * Copy is written as natural institutional text; ownership of the records is
 * recorded only in the private demo manifest, not in public-facing content.
 */
final class DemoDirectorySeeder extends Seeder
{
    /** @var list<array{slug: string, order: int, ar: array{name: string, description: string}, en: array{name: string, description: string}}> */
    private const CATEGORIES = [
        [
            'slug' => 'community-foundations',
            'order' => 10,
            'ar' => [
                'name' => 'المؤسسات المجتمعية المحلية',
                'description' => 'مؤسسات أهلية تنسّق المبادرات المحلية وتدعم أنشطة الأسر في الأحياء.',
            ],
            'en' => [
                'name' => 'Local community foundations',
                'description' => 'Local foundations that coordinate neighbourhood initiatives and support family activities.',
            ],
        ],
        [
            'slug' => 'local-cooperatives',
            'order' => 20,
            'ar' => [
                'name' => 'التعاونيات المحلية',
                'description' => 'تعاونيات يملكها أفراد المجتمع وتعمل في الزراعة والحرف والتخزين الجماعي.',
            ],
            'en' => [
                'name' => 'Local cooperatives',
                'description' => 'Member-owned cooperatives working in farming, crafts and shared storage.',
            ],
        ],
        [
            'slug' => 'education-and-training',
            'order' => 30,
            'ar' => [
                'name' => 'التعليم والتدريب',
                'description' => 'جهات تقدّم دروساً وورشاً عملية للبالغين والشباب في مهارات يومية مفيدة.',
            ],
            'en' => [
                'name' => 'Education and training',
                'description' => 'Groups offering practical lessons and workshops for adults and young people in useful everyday skills.',
            ],
        ],
        [
            'slug' => 'water-and-health',
            'order' => 40,
            'ar' => [
                'name' => 'المياه والصحة',
                'description' => 'مبادرات محلية تعنى بنقاط الماء والتوعية الصحية وممارسات النظافة.',
            ],
            'en' => [
                'name' => 'Water and health',
                'description' => 'Local initiatives focused on water points, health awareness and hygiene practices.',
            ],
        ],
    ];

    /** @var list<array{slug: string, category: string, logo: string, website: string, year: int, order: int, ar: array{name: string, description: string}, en: array{name: string, description: string}}> */
    private const PARTNERS = [
        [
            'slug' => 'wadi-spring-foundation',
            'category' => 'community-foundations',
            'logo' => 'community-garden',
            'website' => 'https://wadi-spring.test',
            'year' => 2022,
            'order' => 10,
            'ar' => [
                'name' => 'مؤسسة نبع الوادي المجتمعية',
                'description' => 'مؤسسة محلية تُنسّق مبادرات الأحياء وتدعم ورش العمل المفيدة للأسرة. للتواصل: ريم القحطاني عبر hello@wadi-spring.test.',
            ],
            'en' => [
                'name' => 'Wadi Spring Community Foundation',
                'description' => 'A local foundation coordinating neighbourhood initiatives and practical family workshops. Contact: Reem Al-Qahtani at hello@wadi-spring.test.',
            ],
        ],
        [
            'slug' => 'rural-orchard-cooperative',
            'category' => 'local-cooperatives',
            'logo' => 'olive-farming',
            'website' => 'https://rural-orchard.test',
            'year' => 2023,
            'order' => 20,
            'ar' => [
                'name' => 'تعاونية بستان الريف',
                'description' => 'تعاونية أسرية تهتم بزراعة الأشجار المثمرة وتنظيم التخزين المجتمعي للمحصول. للتواصل: سالم العتيبي عبر info@rural-orchard.test.',
            ],
            'en' => [
                'name' => 'Rural Orchard Cooperative',
                'description' => 'A family-run cooperative focused on fruit-tree cultivation and shared produce storage. Contact: Salem Al-Otaibi at info@rural-orchard.test.',
            ],
        ],
        [
            'slug' => 'family-circle-association',
            'category' => 'community-foundations',
            'logo' => 'adult-education',
            'website' => 'https://family-circle.test',
            'year' => 2021,
            'order' => 30,
            'ar' => [
                'name' => 'جمعية دائرة الأسر',
                'description' => 'جمعية محلية تنظّم لقاءات توعية ودروس محو أمية للبالغين في إطار مجتمعي مألوف. للتواصل: هدى الشريف عبر contact@family-circle.test.',
            ],
            'en' => [
                'name' => 'Family Circle Association',
                'description' => 'A local association organising awareness meetings and adult literacy classes in a familiar community setting. Contact: Huda Al-Sharif at contact@family-circle.test.',
            ],
        ],
        [
            'slug' => 'echo-water-initiative',
            'category' => 'water-and-health',
            'logo' => 'water-well',
            'website' => 'https://echo-water.test',
            'year' => 2024,
            'order' => 40,
            'ar' => [
                'name' => 'مبادرة صدى الماء',
                'description' => 'مبادرة أهلية تعنى بصيانة نقاط الماء وتوعية الأسر باستخدامها الآمن. للتواصل: طارق منصور عبر hello@echo-water.test.',
            ],
            'en' => [
                'name' => 'Echo Water Initiative',
                'description' => 'A community initiative maintaining water points and sharing guidance on their safe use. Contact: Tareq Mansour at hello@echo-water.test.',
            ],
        ],
        [
            'slug' => 'community-storehouse-cooperative',
            'category' => 'local-cooperatives',
            'logo' => 'food-packing',
            'website' => 'https://community-storehouse.test',
            'year' => 2022,
            'order' => 50,
            'ar' => [
                'name' => 'تعاونية المخزن المجتمعي',
                'description' => 'تعاونية تُجهّز صناديق مؤونة للأسر وتدير مخزوناً محلياً بشكل منظّم. للتواصل: نوران صالح عبر info@community-storehouse.test.',
            ],
            'en' => [
                'name' => 'Community Storehouse Cooperative',
                'description' => 'A cooperative preparing household supply boxes and managing a local stockroom. Contact: Nouran Saleh at info@community-storehouse.test.',
            ],
        ],
        [
            'slug' => 'learning-circle-centre',
            'category' => 'education-and-training',
            'logo' => 'classroom',
            'website' => 'https://learning-circle.test',
            'year' => 2023,
            'order' => 60,
            'ar' => [
                'name' => 'مركز حلقة التعلّم',
                'description' => 'مركز يقدّم دروساً عملية للشباب والبالغين في مهارات القراءة والحساب واستخدام الأدوات اليومية. للتواصل: مازن إبراهيم عبر hello@learning-circle.test.',
            ],
            'en' => [
                'name' => 'Learning Circle Centre',
                'description' => 'A centre offering practical lessons for young people and adults in literacy, numeracy and everyday tools. Contact: Mazen Ibrahim at hello@learning-circle.test.',
            ],
        ],
        [
            'slug' => 'clean-hands-neighbours',
            'category' => 'water-and-health',
            'logo' => 'handwashing',
            'website' => 'https://clean-hands.test',
            'year' => 2025,
            'order' => 70,
            'ar' => [
                'name' => 'جمعية الأيدي النظيفة',
                'description' => 'جمعية حيّية تروّج لممارسات النظافة اليومية وتوزّع مواد تنظيف مبسّطة على الأسر. للتواصل: دانة رشيد عبر contact@clean-hands.test.',
            ],
            'en' => [
                'name' => 'Clean Hands Neighbours',
                'description' => 'A neighbourhood association promoting daily hygiene practices and simple cleaning supplies for households. Contact: Dana Rasheed at contact@clean-hands.test.',
            ],
        ],
        [
            'slug' => 'field-knowledge-forum',
            'category' => 'education-and-training',
            'logo' => 'lecture',
            'website' => 'https://field-knowledge.test',
            'year' => 2024,
            'order' => 80,
            'ar' => [
                'name' => 'منتدى المعرفة الميدانية',
                'description' => 'منتدى محلي يجمع المزارعين والحرفيين لتبادل الخبرات في الزراعة والريّ وإدارة المحاصيل. للتواصل: ياسر الخطيب عبر hello@field-knowledge.test.',
            ],
            'en' => [
                'name' => 'Field Knowledge Forum',
                'description' => 'A local forum where farmers and craftspeople exchange experience in cultivation, irrigation and crop handling. Contact: Yasser Al-Khatib at hello@field-knowledge.test.',
            ],
        ],
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        $categoryIds = $this->seedCategories($manifest);

        foreach (self::PARTNERS as $partner) {
            $partnerId = $manifest->ensureRow(
                'directory_partners',
                'directory_partners',
                $partner['slug'],
                [
                    'directory_partner_category_id' => $categoryIds[$partner['category']],
                    'logo_media_id' => $this->resolveMediaId($manifest, $partner['logo']),
                    'website_url' => $partner['website'],
                    'partnership_year' => $partner['year'],
                    'status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'display_order' => $partner['order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['website_url' => $partner['website']],
            );

            $this->seedPartnerTranslations($manifest, $partnerId, $partner['slug'], $partner);
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function seedCategories(DemoSeedManifest $manifest): array
    {
        $categoryIds = [];

        foreach (self::CATEGORIES as $category) {
            $categoryId = $manifest->ensureRow(
                'directory_partner_categories',
                'directory_partner_categories',
                $category['slug'],
                [
                    'slug' => $category['slug'],
                    'is_active' => true,
                    'display_order' => $category['order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['slug' => $category['slug']],
            );

            $categoryIds[$category['slug']] = $categoryId;

            foreach (['ar', 'en'] as $locale) {
                $manifest->ensureRow(
                    'directory_partner_category_translations',
                    'directory_partner_category_translations',
                    $category['slug'].':'.$locale,
                    [
                        'directory_partner_category_id' => $categoryId,
                        'locale' => $locale,
                        'name' => $category[$locale]['name'],
                        'description' => $category[$locale]['description'],
                        ...DemoSeedManifest::stamps(),
                    ],
                    ['directory_partner_category_id' => $categoryId, 'locale' => $locale],
                );
            }
        }

        return $categoryIds;
    }

    /**
     * @param  array{slug: string, category: string, logo: string, website: string, year: int, order: int, ar: array{name: string, description: string}, en: array{name: string, description: string}}  $partner
     */
    private function seedPartnerTranslations(
        DemoSeedManifest $manifest,
        int|string $partnerId,
        string $slug,
        array $partner,
    ): void {
        foreach (['ar', 'en'] as $locale) {
            $manifest->ensureRow(
                'directory_partner_translations',
                'directory_partner_translations',
                $slug.':'.$locale,
                [
                    'directory_partner_id' => $partnerId,
                    'locale' => $locale,
                    'slug' => $slug,
                    'name' => $partner[$locale]['name'],
                    'description' => $partner[$locale]['description'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['slug' => $slug, 'locale' => $locale],
            );
        }
    }

    private function resolveMediaId(DemoSeedManifest $manifest, string $slug): int|string|null
    {
        $trackedId = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:media:'.$slug);

        if ($trackedId !== null && $trackedId !== '' && ctype_digit($trackedId)) {
            return (int) $trackedId;
        }

        $mediaId = DB::table('media')
            ->where('uuid', DemoSeedManifest::fixedUlid('media:'.$slug))
            ->value('id');

        return is_int($mediaId) || is_string($mediaId) ? $mediaId : null;
    }
}
