<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Internally tracked editorial content for the preview dataset.
 *
 * All public copy is written as natural institutional Arabic with faithful
 * English translations. Budgets and dates are planning figures for 2026; they
 * are not presented as verified results. Existing reference rows such as
 * strategic pillars and homepage sections are only read, never modified.
 */
final class DemoContentSeeder extends Seeder
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    /** @var array<string, int|null> */
    private array $pillarIds = [];

    /** @var array<string, int|null> */
    private array $userIds = [];

    /** @var array<string, int|null> */
    private array $mediaIds = [];

    /** @var array<string, int|null> */
    private array $sectionIds = [];

    /**
     * @param  array<string, int>  $pageIds
     * @param  array<string, int>  $programIds
     * @param  array<string, int>  $projectIds
     * @param  array<string, int>  $articleIds
     * @param  array<string, int>  $publicationIds
     */
    public function run(DemoSeedManifest $manifest): void
    {
        $pageIds = $this->seedPages($manifest);
        $programIds = $this->seedPrograms($manifest);
        $projectIds = $this->seedProjects($manifest, $programIds);
        $categoryIds = $this->seedArticleCategories($manifest);
        $tagIds = $this->seedTags($manifest);
        $articleIds = $this->seedArticles($manifest, $categoryIds);
        $publicationIds = $this->seedPublications($manifest);

        $this->seedArticleTags($manifest, $articleIds, $tagIds);
        $this->seedArticleMedia($manifest, $articleIds);
        $this->seedPublicationMedia($manifest, $publicationIds);
        $this->seedProjectMedia($manifest, $projectIds);
        $this->seedPillarMedia($manifest);
        $this->seedPageMedia($manifest, $pageIds);
        $this->seedContentRevisions($manifest, $pageIds, $programIds, $projectIds, $articleIds, $publicationIds);
        $this->seedHomepageItems($manifest, $projectIds, $articleIds, $publicationIds);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function ensureTranslation(
        DemoSeedManifest $manifest,
        string $table,
        string $module,
        string $ownerKey,
        string $ownerColumn,
        int|string $ownerId,
        string $locale,
        array $values,
    ): void {
        $manifest->ensureRow(
            $table,
            $module,
            $ownerKey.':'.$locale,
            array_merge(
                [$ownerColumn => $ownerId, 'locale' => $locale],
                $values,
                DemoSeedManifest::stamps(),
            ),
            [$ownerColumn => $ownerId, 'locale' => $locale],
        );
    }

    private function ensureMediaPivot(
        DemoSeedManifest $manifest,
        string $table,
        string $ownerColumn,
        int|string $ownerId,
        ?int $mediaId,
        int $sortOrder,
    ): void {
        if ($mediaId === null) {
            return;
        }

        $manifest->ensurePivot(
            $table,
            [$ownerColumn => $ownerId, 'media_id' => $mediaId, 'usage' => 'gallery'],
            ['sort_order' => $sortOrder],
        );
    }

    /**
     * article_tag has no surrogate id and no timestamp columns, so the generic
     * pivot helper cannot be used. The registry key mirrors the manifest's
     * key-based lookup format and keeps the pair idempotent across reruns.
     *
     * @param  array<string, int>  $match
     */
    private function ensureArticleTag(DemoSeedManifest $manifest, array $match): void
    {
        $encoded = $this->encodeMatch($match);

        if (DB::table('article_tag')->where($match)->exists()) {
            return;
        }

        DB::table('article_tag')->insert($match);
        $manifest->putSetting(
            DemoSeedManifest::GROUP,
            'pivot:article_tag:'.hash('sha256', $encoded),
            $encoded,
            'json',
        );
    }

    /** @param  array<string, mixed>  $match */
    private function encodeMatch(array $match): string
    {
        ksort($match);

        return (string) json_encode($match, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function toId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ctype_digit((string) $value) ? (int) $value : null;
    }

    private function userId(DemoSeedManifest $manifest, string $email): ?int
    {
        if (! array_key_exists($email, $this->userIds)) {
            $this->userIds[$email] = $this->toId(
                $manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.$email),
            );
        }

        return $this->userIds[$email];
    }

    private function mediaId(DemoSeedManifest $manifest, string $slug): ?int
    {
        if (! array_key_exists($slug, $this->mediaIds)) {
            $this->mediaIds[$slug] = $this->toId(
                $manifest->settingValue(DemoSeedManifest::GROUP, 'key:media:'.$slug),
            );
        }

        return $this->mediaIds[$slug];
    }

    private function pillarId(string $code): ?int
    {
        if (! array_key_exists($code, $this->pillarIds)) {
            $this->pillarIds[$code] = $this->toId(
                DB::table('strategic_pillars')->where('code', $code)->value('id'),
            );
        }

        return $this->pillarIds[$code];
    }

    private function sectionId(string $key): ?int
    {
        if (! array_key_exists($key, $this->sectionIds)) {
            $this->sectionIds[$key] = $this->toId(
                DB::table('homepage_sections')->where('key', $key)->value('id'),
            );
        }

        return $this->sectionIds[$key];
    }

    /** @return array<string, int> */
    private function seedPages(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach ($this->pageDefinitions() as $definition) {
            $key = $definition['key'];
            $parentId = $definition['parent'] === null ? null : ($ids[$definition['parent']] ?? null);
            $resolver = static function () use ($key): ?int {
                $pageId = DB::table('page_translations')
                    ->where('locale', 'ar')
                    ->where('slug', $key)
                    ->value('page_id');

                return $pageId === null ? null : (int) $pageId;
            };

            $pageId = (int) $manifest->ensureRow(
                'pages',
                'page',
                $key,
                [
                    'parent_id' => $parentId,
                    'status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'author_id' => $this->userId($manifest, $definition['author']),
                    'reviewer_id' => $this->userId($manifest, $definition['reviewer']),
                    'display_order' => $definition['order'],
                    'is_system' => false,
                    ...DemoSeedManifest::stamps(),
                ],
                [],
                $resolver,
            );

            $ids[$key] = $pageId;

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'page_translations',
                    'page_translation',
                    $key,
                    'page_id',
                    $pageId,
                    $locale,
                    [
                        'slug' => $key,
                        'title' => $copy['title'],
                        'summary' => $copy['summary'],
                        'body' => $copy['body'],
                        'seo_title' => $copy['seo_title'],
                        'seo_description' => $copy['seo_description'],
                    ],
                );
            }
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedPrograms(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach ($this->programDefinitions() as $definition) {
            $code = $definition['code'];

            $ids[$code] = (int) $manifest->ensureRow(
                'programs',
                'program',
                $code,
                [
                    'code' => $code,
                    'strategic_pillar_id' => $this->pillarId($definition['pillar']),
                    'program_manager_id' => $this->userId($manifest, $definition['manager']),
                    'responsible_unit' => $definition['unit'],
                    'category' => $definition['category'],
                    'proposed_budget' => $definition['proposed_budget'],
                    'approved_budget' => $definition['approved_budget'],
                    'budget_currency' => 'USD',
                    'status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'featured_image_id' => $this->mediaId($manifest, $definition['media']),
                    'display_order' => $definition['order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['code' => $code],
            );

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'program_translations',
                    'program_translation',
                    $code,
                    'program_id',
                    $ids[$code],
                    $locale,
                    [
                        'slug' => $definition['slug'],
                        'title' => $copy['title'],
                        'summary' => $copy['summary'],
                        'description' => $copy['description'],
                        'objectives' => $copy['objectives'],
                        'target_groups' => $copy['target_groups'],
                        'geographic_coverage' => 'قطاع غزة',
                        'seo_title' => $copy['seo_title'],
                        'seo_description' => $copy['seo_description'],
                    ],
                );
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $programIds
     * @return array<string, int>
     */
    private function seedProjects(DemoSeedManifest $manifest, array $programIds): array
    {
        $ids = [];

        foreach ($this->projectDefinitions() as $definition) {
            $code = $definition['code'];

            $ids[$code] = (int) $manifest->ensureRow(
                'projects',
                'project',
                $code,
                [
                    'code' => $code,
                    'program_id' => $programIds[$definition['program']] ?? null,
                    'strategic_pillar_id' => $this->pillarId($definition['pillar']),
                    'project_manager_id' => $this->userId($manifest, $definition['manager']),
                    'responsible_team' => $definition['team'],
                    'proposed_budget' => $definition['proposed_budget'],
                    'approved_budget' => $definition['approved_budget'],
                    'budget_currency' => 'USD',
                    'status' => $definition['status'],
                    'workflow_status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'actual_start_date' => $definition['actual_start_date'],
                    'actual_end_date' => $definition['actual_end_date'],
                    'featured_image_id' => $this->mediaId($manifest, $definition['media']),
                    'display_order' => $definition['order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['code' => $code],
            );

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'project_translations',
                    'project_translation',
                    $code,
                    'project_id',
                    $ids[$code],
                    $locale,
                    [
                        'slug' => $definition['slug'],
                        'title' => $copy['title'],
                        'summary' => $copy['summary'],
                        'description' => $copy['description'],
                        'objectives' => $copy['objectives'],
                        'target_groups' => $copy['target_groups'],
                        'geographic_coverage' => $locale === 'ar' ? 'قطاع غزة' : 'Gaza Strip',
                        'seo_title' => $copy['seo_title'],
                        'seo_description' => $copy['seo_description'],
                    ],
                );
            }
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedArticleCategories(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach ($this->articleCategoryDefinitions() as $definition) {
            $slug = $definition['slug'];

            $ids[$slug] = (int) $manifest->ensureRow(
                'article_categories',
                'article_category',
                $slug,
                [
                    'slug' => $slug,
                    'display_order' => $definition['order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['slug' => $slug],
            );

            foreach (self::LOCALES as $locale) {
                $this->ensureTranslation(
                    $manifest,
                    'article_category_translations',
                    'article_category_translation',
                    $slug,
                    'article_category_id',
                    $ids[$slug],
                    $locale,
                    [
                        'name' => $definition[$locale]['name'],
                        'description' => $definition[$locale]['description'],
                    ],
                );
            }
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedTags(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach ($this->tagDefinitions() as $definition) {
            $slug = $definition['slug'];

            $ids[$slug] = (int) $manifest->ensureRow(
                'tags',
                'tag',
                $slug,
                [
                    'slug' => $slug,
                    ...DemoSeedManifest::stamps(),
                ],
                ['slug' => $slug],
            );

            foreach (self::LOCALES as $locale) {
                $this->ensureTranslation(
                    $manifest,
                    'tag_translations',
                    'tag_translation',
                    $slug,
                    'tag_id',
                    $ids[$slug],
                    $locale,
                    ['name' => $definition[$locale]],
                );
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $categoryIds
     * @return array<string, int>
     */
    private function seedArticles(DemoSeedManifest $manifest, array $categoryIds): array
    {
        $ids = [];

        foreach ($this->articleDefinitions() as $definition) {
            $code = $definition['code'];

            $ids[$code] = (int) $manifest->ensureRow(
                'articles',
                'article',
                $code,
                [
                    'article_category_id' => $categoryIds[$definition['category']] ?? null,
                    'strategic_pillar_id' => $this->pillarId($definition['pillar']),
                    'author_id' => $this->userId($manifest, $definition['author']),
                    'status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'event_date' => $definition['event_date'],
                    'is_featured' => $definition['featured'],
                    'cover_media_id' => $this->mediaId($manifest, $definition['media']),
                    'display_order' => $definition['order'],
                    ...DemoSeedManifest::stamps(),
                ],
            );

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'article_translations',
                    'article_translation',
                    $code,
                    'article_id',
                    $ids[$code],
                    $locale,
                    [
                        'slug' => $definition['slug'],
                        'title' => $copy['title'],
                        'excerpt' => $copy['excerpt'],
                        'body' => $copy['body'],
                        'seo_title' => $copy['seo_title'],
                        'seo_description' => $copy['excerpt'],
                    ],
                );
            }
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedPublications(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach ($this->publicationDefinitions() as $definition) {
            $code = $definition['code'];

            $ids[$code] = (int) $manifest->ensureRow(
                'publications',
                'publication',
                $code,
                [
                    'category' => $definition['category'],
                    'status' => 'published',
                    'published_at' => DemoSeedManifest::BASE_TIME,
                    'publication_date' => $definition['publication_date'],
                    'strategic_pillar_id' => $this->pillarId($definition['pillar']),
                    'document_media_id' => null,
                    'cover_media_id' => $this->mediaId($manifest, $definition['media']),
                    'version' => $definition['version'],
                    'is_restricted' => false,
                    'display_order' => $definition['order'],
                    ...DemoSeedManifest::stamps(),
                ],
            );

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'publication_translations',
                    'publication_translation',
                    $code,
                    'publication_id',
                    $ids[$code],
                    $locale,
                    [
                        'slug' => $definition['slug'],
                        'title' => $copy['title'],
                        'description' => $copy['description'],
                        'seo_title' => $copy['seo_title'],
                        'seo_description' => $copy['description'],
                    ],
                );
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $articleIds
     * @param  array<string, int>  $tagIds
     */
    private function seedArticleTags(DemoSeedManifest $manifest, array $articleIds, array $tagIds): void
    {
        foreach ($this->articleTagPairs() as [$articleKey, $tagSlug]) {
            if (! isset($articleIds[$articleKey], $tagIds[$tagSlug])) {
                continue;
            }

            $this->ensureArticleTag($manifest, [
                'article_id' => $articleIds[$articleKey],
                'tag_id' => $tagIds[$tagSlug],
            ]);
        }
    }

    /** @param  array<string, int>  $articleIds */
    private function seedArticleMedia(DemoSeedManifest $manifest, array $articleIds): void
    {
        foreach ($this->articleDefinitions() as $definition) {
            $this->ensureMediaPivot(
                $manifest,
                'article_media',
                'article_id',
                $articleIds[$definition['code']] ?? 0,
                $this->mediaId($manifest, $definition['media']),
                $definition['order'],
            );
        }
    }

    /** @param  array<string, int>  $publicationIds */
    private function seedPublicationMedia(DemoSeedManifest $manifest, array $publicationIds): void
    {
        foreach ($this->publicationDefinitions() as $definition) {
            $this->ensureMediaPivot(
                $manifest,
                'publication_media',
                'publication_id',
                $publicationIds[$definition['code']] ?? 0,
                $this->mediaId($manifest, $definition['media']),
                $definition['order'],
            );
        }
    }

    /** @param  array<string, int>  $projectIds */
    private function seedProjectMedia(DemoSeedManifest $manifest, array $projectIds): void
    {
        foreach ($this->projectDefinitions() as $definition) {
            $this->ensureMediaPivot(
                $manifest,
                'project_media',
                'project_id',
                $projectIds[$definition['code']] ?? 0,
                $this->mediaId($manifest, $definition['media']),
                $definition['order'],
            );
        }
    }

    private function seedPillarMedia(DemoSeedManifest $manifest): void
    {
        $attachments = [
            ['02' => 'food-donations'],
            ['03' => 'farmer-harvest'],
            ['04' => 'health-check'],
            ['05' => 'water-well'],
            ['06' => 'adult-education'],
            ['08' => 'tree-planting'],
        ];

        foreach ($attachments as $index => $attachment) {
            $code = (string) array_key_first($attachment);
            $slug = (string) $attachment[$code];

            $this->ensureMediaPivot(
                $manifest,
                'pillar_media',
                'strategic_pillar_id',
                $this->pillarId($code) ?? 0,
                $this->mediaId($manifest, $slug),
                $index + 1,
            );
        }
    }

    /** @param  array<string, int>  $pageIds */
    private function seedPageMedia(DemoSeedManifest $manifest, array $pageIds): void
    {
        $attachments = [
            ['about' => 'community-garden'],
            ['about' => 'classroom'],
            ['transparency' => 'donation-sorting'],
            ['transparency' => 'food-packing'],
            ['privacy' => 'soap'],
            ['volunteer' => 'tree-planting'],
        ];

        foreach ($attachments as $index => $attachment) {
            $key = (string) array_key_first($attachment);
            $slug = (string) $attachment[$key];

            $this->ensureMediaPivot(
                $manifest,
                'page_media',
                'page_id',
                $pageIds[$key] ?? 0,
                $this->mediaId($manifest, $slug),
                $index + 1,
            );
        }
    }

    /**
     * @param  array<string, int>  $pageIds
     * @param  array<string, int>  $programIds
     * @param  array<string, int>  $projectIds
     * @param  array<string, int>  $articleIds
     * @param  array<string, int>  $publicationIds
     */
    private function seedContentRevisions(
        DemoSeedManifest $manifest,
        array $pageIds,
        array $programIds,
        array $projectIds,
        array $articleIds,
        array $publicationIds,
    ): void {
        $definitions = [
            ['App\\Models\\Project', 'PRJ-001', $projectIds['PRJ-001'] ?? 0, 'ar', 'الخطة التفصيلية لتوزيع الطرود الغذائية', 'approved', '2026-01-18 10:15:00', 'اعتماد التوزيع المبدئي ضمن خطة الربع الأول.', ['budget' => 165000, 'currency' => 'USD', 'status' => 'ongoing']],
            ['App\\Models\\Project', 'PRJ-002', $projectIds['PRJ-002'] ?? 0, 'ar', 'مراجعة آلية العمل في المطابخ المجتمعية', 'published', '2026-02-08 11:30:00', 'تحديث معايير الاستحقاق وإجراءات التسليم.', ['budget' => 112000, 'currency' => 'USD', 'status' => 'completed']],
            ['App\\Models\\Project', 'PRJ-003', $projectIds['PRJ-003'] ?? 0, 'ar', 'تخطيط الحدائق المنزلية في المناطق الحضرية', 'pending_review', '2026-03-02 09:45:00', 'مسودة مرحلة اختيار المواقع.', ['budget' => 88000, 'currency' => 'USD', 'status' => 'ongoing']],
            ['App\\Models\\Project', 'PRJ-004', $projectIds['PRJ-004'] ?? 0, 'ar', 'مسار التدريب المهني للأسر المنتجة', 'approved', '2026-03-14 12:00:00', 'اعتماد قائمة المهن المستهدفة.', ['budget' => 125000, 'currency' => 'USD', 'status' => 'ongoing']],
            ['App\\Models\\Project', 'PRJ-005', $projectIds['PRJ-005'] ?? 0, 'en', 'Psychosocial support group plan', 'draft', '2026-04-06 08:30:00', 'Initial planning note for group sessions.', ['budget' => 84000, 'currency' => 'USD', 'status' => 'planned']],
            ['App\\Models\\Project', 'PRJ-006', $projectIds['PRJ-006'] ?? 0, 'en', 'Water source maintenance schedule', 'approved', '2026-04-22 13:15:00', 'Schedule reviewed against field priorities.', ['budget' => 100000, 'currency' => 'USD', 'status' => 'ongoing']],
            ['App\\Models\\Project', 'PRJ-007', $projectIds['PRJ-007'] ?? 0, 'en', 'Temporary learning space setup', 'pending_review', '2026-05-11 10:00:00', 'Draft layout and supply list.', ['budget' => 70000, 'currency' => 'USD', 'status' => 'planned']],
            ['App\\Models\\Project', 'PRJ-008', $projectIds['PRJ-008'] ?? 0, 'en', 'Neighbourhood volunteer committee plan', 'published', '2026-06-01 09:20:00', 'Final working plan for volunteer committees.', ['budget' => 56000, 'currency' => 'USD', 'status' => 'completed']],
            ['App\\Models\\Program', 'PRG-001', $programIds['PRG-001'] ?? 0, 'ar', 'خطة برنامج الأمن الغذائي لعام 2026', 'approved', '2026-01-10 09:00:00', 'اعتماد المخصصات التخطيطية للبرنامج.', ['budget' => 420000, 'currency' => 'USD', 'pillar' => '02']],
            ['App\\Models\\Program', 'PRG-002', $programIds['PRG-002'] ?? 0, 'ar', 'برنامج التمكين الاقتصادي وسبل العيش', 'approved', '2026-01-12 09:30:00', 'تأكيد الأولويات والمسارات المهنية.', ['budget' => 320000, 'currency' => 'USD', 'pillar' => '03']],
            ['App\\Models\\Program', 'PRG-003', $programIds['PRG-003'] ?? 0, 'ar', 'إطار الحماية والدعم النفسي والاجتماعي', 'pending_review', '2026-02-03 11:00:00', 'مراجعة نطاق الخدمات المقترحة.', ['budget' => 270000, 'currency' => 'USD', 'pillar' => '04']],
            ['App\\Models\\Program', 'PRG-004', $programIds['PRG-004'] ?? 0, 'ar', 'خطة الصحة والمياه والإصحاح البيئي', 'approved', '2026-02-19 10:45:00', 'تحديد أولويات الصيانة والتوعية.', ['budget' => 230000, 'currency' => 'USD', 'pillar' => '05']],
            ['App\\Models\\Program', 'PRG-005', $programIds['PRG-005'] ?? 0, 'en', 'Education and youth capacity programme', 'draft', '2026-03-08 09:10:00', 'Draft programme framing for learning spaces.', ['budget' => 210000, 'currency' => 'USD', 'pillar' => '06']],
            ['App\\Models\\Page', 'about', $pageIds['about'] ?? 0, 'ar', 'تعريف مؤسسي محدث', 'published', '2026-04-02 08:50:00', 'توحيد الرسالة والرؤية في النص التعريفي.', ['locale' => 'ar', 'status' => 'published']],
            ['App\\Models\\Page', 'transparency', $pageIds['transparency'] ?? 0, 'en', 'Transparency and accountability note', 'approved', '2026-04-20 12:10:00', 'Reviewed disclosure and feedback sections.', ['locale' => 'en', 'status' => 'approved']],
            ['App\\Models\\Article', 'ART-001', $articleIds['ART-001'] ?? 0, 'ar', 'ملخص خطة توزيع الطرود', 'published', '2026-05-06 09:40:00', 'مراجعة نهائية قبل النشر.', ['locale' => 'ar', 'category' => 'field-updates']],
            ['App\\Models\\Article', 'ART-002', $articleIds['ART-002'] ?? 0, 'ar', 'معايير العمل في المطابخ المجتمعية', 'approved', '2026-05-24 11:20:00', 'اعتماد صياغة معايير الاستحقاق.', ['locale' => 'ar', 'category' => 'food-security']],
            ['App\\Models\\Article', 'ART-003', $articleIds['ART-003'] ?? 0, 'en', 'Home gardens planning note', 'draft', '2026-06-12 08:15:00', 'Early draft of the garden component.', ['locale' => 'en', 'category' => 'food-security']],
            ['App\\Models\\Publication', 'PUB-001', $publicationIds['PUB-001'] ?? 0, 'en', 'Food security plan summary 2026', 'published', '2026-07-02 10:30:00', 'Approved bilingual publication record.', ['category' => 'strategic_plan', 'version' => '2026/1']],
            ['App\\Models\\Publication', 'PUB-002', $publicationIds['PUB-002'] ?? 0, 'ar', 'دليل النظافة وإدارة المياه', 'approved', '2026-07-18 13:00:00', 'اعتماد نسخة المراجعة الأولى.', ['category' => 'policy', 'version' => '1.0']],
        ];

        foreach ($definitions as $index => [$type, $naturalKey, $ownerId, $locale, $title, $status, $at, $note, $payload]) {
            $actor = $index % 2 === 0 ? 'hana.rafiq@pcdr.test' : 'karim.zuhair@pcdr.test';

            $manifest->ensureRow(
                'content_revisions',
                'revision',
                $naturalKey.':'.$locale.':'.$status,
                [
                    'revisionable_type' => $type,
                    'revisionable_id' => $ownerId,
                    'locale' => $locale,
                    'title' => $title,
                    'status' => $status,
                    'payload' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'actor_id' => $this->userId($manifest, $actor),
                    'note' => $note,
                    'created_at' => $at,
                ],
                [
                    'revisionable_type' => $type,
                    'revisionable_id' => $ownerId,
                    'locale' => $locale,
                    'title' => $title,
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $projectIds
     * @param  array<string, int>  $articleIds
     * @param  array<string, int>  $publicationIds
     */
    private function seedHomepageItems(
        DemoSeedManifest $manifest,
        array $projectIds,
        array $articleIds,
        array $publicationIds,
    ): void {
        foreach ($this->homepageItemDefinitions($projectIds, $articleIds, $publicationIds) as $definition) {
            $sectionId = $this->sectionId($definition['section']);

            if ($sectionId === null || $definition['item_id'] === null) {
                continue;
            }

            $itemKey = $definition['item_key'];

            $itemId = (int) $manifest->ensureRow(
                'homepage_section_items',
                'homepage_section_item',
                $itemKey,
                [
                    'homepage_section_id' => $sectionId,
                    'item_type' => $definition['item_type'],
                    'item_id' => $definition['item_id'],
                    'media_id' => $this->mediaId($manifest, $definition['media']),
                    'item_key' => $itemKey,
                    'tab' => $definition['tab'],
                    'is_featured' => $definition['featured'],
                    'motif_key' => null,
                    'is_published' => true,
                    'sort_order' => $definition['sort_order'],
                    ...DemoSeedManifest::stamps(),
                ],
                ['homepage_section_id' => $sectionId, 'item_key' => $itemKey],
            );

            foreach (self::LOCALES as $locale) {
                $copy = $definition[$locale];

                $this->ensureTranslation(
                    $manifest,
                    'homepage_section_item_translations',
                    'homepage_section_item_translation',
                    $itemKey,
                    'homepage_section_item_id',
                    $itemId,
                    $locale,
                    [
                        'label' => $copy['label'],
                        'title' => $copy['title'],
                        'body' => $copy['body'],
                        'date_label' => $copy['date_label'],
                        'cta_label' => $copy['cta_label'],
                        'url' => $copy['url'],
                        'caption' => $copy['caption'],
                        'unit' => null,
                    ],
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageDefinitions(): array
    {
        return [
            [
                'key' => 'about',
                'parent' => null,
                'order' => 10,
                'author' => 'sami.haddad@pcdr.test',
                'reviewer' => 'layla.nasser@pcdr.test',
                'ar' => [
                    'title' => 'عن الجمعية',
                    'summary' => 'تعريف بمسار العمل المؤسسي وأساليب التنسيق مع المجتمعات المحلية.',
                    'body' => 'تعمل جمعية العمل التشاركي في الأزمات والكوارث الخيرية على دعم المجتمעות المتضررة من خلال برامج مخططة في الأمن الغذائي وسبل العيش والصحة والمياه والتعليم والحماية الاجتماعية. يعتمد العمل على تقييم الاحتياجات، وتنسيق الجهود مع الفاعلين المحليين، وتوثيق المراحل التنفيذية بما يعزز الشفافية ويحسن جودة الخدمات المقدمة.',
                    'seo_title' => 'عن جمعية العمل التشاركي في الأزمات والكوارث',
                    'seo_description' => 'تعريف بالجمعية ومحاور عملها في الاستجابة الإنسانية والتعافي والتنمية المستدامة.',
                ],
                'en' => [
                    'title' => 'About PCDR',
                    'summary' => 'An overview of the institutional approach and coordination with local communities.',
                    'body' => 'The Participatory Community for Disaster Response works with communities affected by crises through planned programmes in food security, livelihoods, health, water, education and social protection. The approach combines needs assessment, coordination with local actors and documentation of delivery stages to strengthen transparency and service quality.',
                    'seo_title' => 'About the Participatory Community for Disaster Response',
                    'seo_description' => 'An introduction to the organisation and its work across humanitarian response, recovery and sustainable development.',
                ],
            ],
            [
                'key' => 'transparency',
                'parent' => null,
                'order' => 20,
                'author' => 'sami.haddad@pcdr.test',
                'reviewer' => 'karim.zuhair@pcdr.test',
                'ar' => [
                    'title' => 'الشفافية والمساءلة',
                    'summary' => 'سياسات التوثيق وإدارة المعلومات وقنوات الملاحظات والشكاوى.',
                    'body' => 'تلتزم الجمعية بتوثيق مراحل التخطيط والتنفيذ والتقارير المالية بما يدعم الرقابة الداخلية ويتيح للجمهور فهم آليات العمل. تشمل السياسات حماية البيانات الشخصية، وتسجيل القرارات، ومراجعة المؤشرات، وتوفير قنوات آمنة لتلقي الملاحظات والشكاوى ومعالجتها وفق إجراءات معلنة.',
                    'seo_title' => 'الشفافية والمساءلة في الجمعية',
                    'seo_description' => 'سياسات التوثيق وحماية البيانات وقنوات الشكاوى والملاحظات.',
                ],
                'en' => [
                    'title' => 'Transparency and accountability',
                    'summary' => 'Documentation, information management and feedback channels.',
                    'body' => 'The organisation documents planning, delivery and financial reporting stages to support internal oversight and public understanding. Policies cover personal-data protection, decision records, indicator review and safe channels for feedback and complaints handled through published procedures.',
                    'seo_title' => 'Transparency and accountability at PCDR',
                    'seo_description' => 'Documentation policies, data protection and complaint and feedback channels.',
                ],
            ],
            [
                'key' => 'privacy',
                'parent' => 'about',
                'order' => 30,
                'author' => 'hana.rafiq@pcdr.test',
                'reviewer' => 'layla.nasser@pcdr.test',
                'ar' => [
                    'title' => 'سياسة الخصوصية',
                    'summary' => 'كيفية التعامل مع البيانات المقدمة عبر الموقع والتحكم بها.',
                    'body' => 'تستخدم البيانات المقدمة عبر نماذج التواصل لأغراض الرد على الاستفسارات وتطوير الخدمات فقط. تُحفظ المعلومات الحساسة داخل أنظمة محدودة الوصول، ولا تُعرض بيانات الأفراد على الموقع العام. يمكن لأصحاب البيانات طلب مراجعة معلوماتهم أو تصحيحها من خلال قنوات التواصل المعتمدة.',
                    'seo_title' => 'سياسة الخصوصية',
                    'seo_description' => 'بيانات عن كيفية جمع المعلومات واستخدامها وحمايتها على الموقع.',
                ],
                'en' => [
                    'title' => 'Privacy policy',
                    'summary' => 'How information submitted through the website is handled and controlled.',
                    'body' => 'Information submitted through website forms is used only to answer enquiries and improve services. Sensitive information is stored in access-controlled systems and personal details are not displayed publicly. Individuals may request review or correction of their information through approved contact channels.',
                    'seo_title' => 'Privacy policy',
                    'seo_description' => 'How information is collected, used and protected on the website.',
                ],
            ],
            [
                'key' => 'volunteer',
                'parent' => 'about',
                'order' => 40,
                'author' => 'hana.rafiq@pcdr.test',
                'reviewer' => 'sami.haddad@pcdr.test',
                'ar' => [
                    'title' => 'التطوع والمشاركة المجتمعية',
                    'summary' => 'مسارات المشاركة المتاحة ومبادئ التطوع المجتمعي.',
                    'body' => 'يمكن للأفراد التعبير عن اهتمامهم بالتطوع من خلال نموذج المشاركة المتاح على الموقع. تتم مراجعة الطلبات وفق المجالات المعلنة والقدرات المطلوبة، مع الالتزام بمبادئ الاحترام والسلامة وحماية البيانات. تستخدم فرق العمل خططاً واضحة لتوزيع المهام وتوثيق المشاركة.',
                    'seo_title' => 'التطوع والمشاركة المجتمعية',
                    'seo_description' => 'مسارات التطوع ومبادئ المشاركة المجتمعية الآمنة.',
                ],
                'en' => [
                    'title' => 'Volunteering and community participation',
                    'summary' => 'Available participation pathways and community volunteering principles.',
                    'body' => 'Individuals can express interest in volunteering through the participation form on the website. Requests are reviewed against published focus areas and required skills, with respect for safety, dignity and data protection. Teams use clear plans to assign tasks and document participation.',
                    'seo_title' => 'Volunteering and community participation',
                    'seo_description' => 'Volunteering pathways and principles for safe community participation.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function programDefinitions(): array
    {
        return [
            [
                'code' => 'PRG-001',
                'slug' => 'food-security-programme',
                'pillar' => '02',
                'manager' => 'sami.haddad@pcdr.test',
                'unit' => 'وحدة الأمن الغذائي',
                'category' => 'food_security',
                'proposed_budget' => '420000.00',
                'approved_budget' => '410000.00',
                'start_date' => '2026-01-15',
                'end_date' => '2026-12-20',
                'media' => 'food-donations',
                'order' => 10,
                'ar' => [
                    'title' => 'برنامج الأمن الغذائي وتحسين الظروف المعيشية',
                    'summary' => 'برنامج مخطط لدعم وصول الأسر إلى الغذاء وتحسين ممارسات التخزين والتوزيع.',
                    'description' => 'يرتكز البرنامج على تقييم احتياجات الأسر وتنسيق عمليات التوريد والتخزين والتوزيع وفق معايير معلنة، مع متابعة جودة الطرود وملاءمتها للظروف المحلية.',
                    'objectives' => 'تحسين انتظام وصول المواد الغذائية الأساسية؛ تعزيز ممارسات التخزين الآمن؛ رفع جودة التوثيق في عمليات التوزيع.',
                    'target_groups' => 'أسر محدودة الدخل، ومقدمو الرعاية في الأسر، والفرق المجتمعية المشاركة في التوزيع.',
                    'seo_title' => 'برنامج الأمن الغذائي',
                    'seo_description' => 'برنامج مخطط لدعم الأسر وتحسين عمليات التوريد والتخزين والتوزيع.',
                ],
                'en' => [
                    'title' => 'Food security and improved living conditions programme',
                    'summary' => 'A planned programme to support household food access and improve storage and distribution practices.',
                    'description' => 'The programme centres on household needs assessment and coordinated procurement, storage and distribution against published criteria, with follow-up on parcel quality and local suitability.',
                    'objectives' => 'Improve regular access to staple food; strengthen safe storage practices; improve documentation quality across distribution.',
                    'target_groups' => 'Low-income households, caregivers and community teams involved in distribution.',
                    'seo_title' => 'Food security programme',
                    'seo_description' => 'A planned programme supporting households and improving procurement, storage and distribution.',
                ],
            ],
            [
                'code' => 'PRG-002',
                'slug' => 'livelihoods-programme',
                'pillar' => '03',
                'manager' => 'layla.nasser@pcdr.test',
                'unit' => 'وحدة سبل العيش',
                'category' => 'livelihoods',
                'proposed_budget' => '320000.00',
                'approved_budget' => '305000.00',
                'start_date' => '2026-02-01',
                'end_date' => '2026-11-30',
                'media' => 'farmer-harvest',
                'order' => 20,
                'ar' => [
                    'title' => 'برنامج التنمية الاقتصادية وسبل العيش',
                    'summary' => 'مسار تدريبي مخطط لدعم المهارات والأنشطة المدرة للدخل على المستوى الأسري.',
                    'description' => 'يجمع البرنامج بين تقييم المهارات وورش العمل التطبيقية وبناء خطط عمل فردية، مع متابعة دورية لقياس أثر التدريب على انتظام الأنشطة الاقتصادية.',
                    'objectives' => 'تطوير مهارات عملية قابلة للتطبيق؛ تحسين التخطيط الأسري للأنشطة المدرة للدخل؛ تعزيز الوصول إلى أدوات بسيطة ومواد خام.',
                    'target_groups' => 'الأسر المنتجة، والخريجون غير العاملين، وأصحاب الحرف الصغيرة.',
                    'seo_title' => 'برنامج سبل العيش',
                    'seo_description' => 'مسار تدريبي مخطط لدعم المهارات والأنشطة المدرة للدخل.',
                ],
                'en' => [
                    'title' => 'Economic development and livelihoods programme',
                    'summary' => 'A planned training pathway supporting skills and household-level income activities.',
                    'description' => 'The programme combines skills assessment, practical workshops and individual action plans, with periodic follow-up on how training affects the regularity of economic activities.',
                    'objectives' => 'Develop practical and applicable skills; improve household planning for income activities; improve access to simple tools and inputs.',
                    'target_groups' => 'Home-based producers, unemployed graduates and small-scale craftspeople.',
                    'seo_title' => 'Livelihoods programme',
                    'seo_description' => 'A planned training pathway supporting skills and income-generating activities.',
                ],
            ],
            [
                'code' => 'PRG-003',
                'slug' => 'psychosocial-support-programme',
                'pillar' => '04',
                'manager' => 'karim.zuhair@pcdr.test',
                'unit' => 'وحدة الحماية والدعم النفسي',
                'category' => 'protection',
                'proposed_budget' => '270000.00',
                'approved_budget' => '260000.00',
                'start_date' => '2026-03-01',
                'end_date' => '2026-12-15',
                'media' => 'adult-education',
                'order' => 30,
                'ar' => [
                    'title' => 'برنامج الحماية الاجتماعية والدعم النفسي والاجتماعي',
                    'summary' => 'برنامج مخطط لتنظيم جلسات الدعم والأنشطة الاجتماعية الآمنة للأطفال والبالغين.',
                    'description' => 'يعتمد البرنامج على إرشادات حماية الطفل والخصوصية، ويشمل جلسات توعية وتجميع ملاحظات المشاركين في بيئة منظمة تحترم الخصوصية وتجنب التعريف بالأفراد.',
                    'objectives' => 'توفير مساحات آمنة للأنشطة الاجتماعية؛ تحسين مهارات التعامل مع الضغوط؛ تعزيز آليات الإحالة المعتمدة.',
                    'target_groups' => 'الأطفال والمراهقون ومقدمو الرعاية والفرق المجتمعية الماهرة بالدعم النفسي.',
                    'seo_title' => 'برنامج الدعم النفسي والاجتماعي',
                    'seo_description' => 'برنامج مخطط لجلسات الدعم والأنشطة الاجتماعية الآمنة.',
                ],
                'en' => [
                    'title' => 'Social protection and psychosocial support programme',
                    'summary' => 'A planned programme organising safe support sessions and social activities for children and adults.',
                    'description' => 'The programme follows child-protection and privacy guidance and includes awareness sessions and structured feedback collection that respects privacy and avoids identifying individuals.',
                    'objectives' => 'Provide safe spaces for social activities; improve coping skills; strengthen approved referral mechanisms.',
                    'target_groups' => 'Children, adolescents, caregivers and trained community support teams.',
                    'seo_title' => 'Psychosocial support programme',
                    'seo_description' => 'A planned programme for safe support sessions and social activities.',
                ],
            ],
            [
                'code' => 'PRG-004',
                'slug' => 'health-water-sanitation-programme',
                'pillar' => '05',
                'manager' => 'reem.adwan@pcdr.test',
                'unit' => 'وحدة الصحة والمياه والإصحاح',
                'category' => 'wash',
                'proposed_budget' => '230000.00',
                'approved_budget' => '225000.00',
                'start_date' => '2026-04-01',
                'end_date' => '2026-10-31',
                'media' => 'handwashing',
                'order' => 40,
                'ar' => [
                    'title' => 'برنامج الصحة العامة والمياه والإصحاح البيئي',
                    'summary' => 'برنامج مخطط يركز على صيانة نقاط الماء وتحسين ممارسات النظافة اليومية.',
                    'description' => 'يشمل البرنامج جرد نقاط الماء، وتحديد احتياجات الصيانة البسيطة، وتنظيم جلسات توعية حول غسل اليدين واستخدام المواد المنظفة بطريقة آمنة.',
                    'objectives' => 'تحسين موثوقية نقاط الماء؛ نشر ممارسات النظافة؛ توثيق أعمال الصيانة والتوعية.',
                    'target_groups' => 'الأسر في المناطق المخدمة، وفرق صيانة المياه، والمدارس المجتمعية.',
                    'seo_title' => 'برنامج المياه والإصحاح',
                    'seo_description' => 'برنامج مخطط لصيانة نقاط الماء وتحسين ممارسات النظافة.',
                ],
                'en' => [
                    'title' => 'Public health, water and environmental sanitation programme',
                    'summary' => 'A planned programme focused on water-point maintenance and everyday hygiene practices.',
                    'description' => 'The programme includes water-point inventories, identification of simple maintenance needs and awareness sessions on handwashing and safe use of cleaning materials.',
                    'objectives' => 'Improve water-point reliability; promote hygiene practices; document maintenance and awareness work.',
                    'target_groups' => 'Households in served areas, water maintenance teams and community schools.',
                    'seo_title' => 'Water and sanitation programme',
                    'seo_description' => 'A planned programme for water-point maintenance and hygiene improvement.',
                ],
            ],
            [
                'code' => 'PRG-005',
                'slug' => 'education-youth-programme',
                'pillar' => '06',
                'manager' => 'nadia.qamar@pcdr.test',
                'unit' => 'وحدة التعليم والشباب',
                'category' => 'education',
                'proposed_budget' => '210000.00',
                'approved_budget' => '200000.00',
                'start_date' => '2026-05-01',
                'end_date' => '2026-12-10',
                'media' => 'classroom',
                'order' => 50,
                'ar' => [
                    'title' => 'برنامج التعليم وتنمية قدرات الأطفال والشباب',
                    'summary' => 'برنامج مخطط لتجهيز مساحات تعلم وتنظيم أنشطة تعليمية مكملة.',
                    'description' => 'يعمل البرنامج على تجهيز مساحات التعلم بالمستلزمات الأساسية وتنظيم جلسات دعم تعليمي وأنشطة ثقافية، مع متابعة الحضور وقياس التغير في مهارات المشاركين.',
                    'objectives' => 'تجهيز مساحات تعلم مناسبة؛ دعم المهارات الأساسية؛ تعزيز مشاركة الشباب في الأنشطة المجتمعية.',
                    'target_groups' => 'الأطفال في سن المدرسة، والشباب، ومعلمو المجتمع.',
                    'seo_title' => 'برنامج التعليم والشباب',
                    'seo_description' => 'برنامج مخطط لتجهيز مساحات التعلم وتنظيم أنشطة تعليمية مكملة.',
                ],
                'en' => [
                    'title' => 'Education and child and youth capacity programme',
                    'summary' => 'A planned programme to prepare learning spaces and organise supplementary educational activities.',
                    'description' => 'The programme equips learning spaces with essential supplies and organises educational support and cultural activities, tracking attendance and changes in participant skills.',
                    'objectives' => 'Prepare suitable learning spaces; support foundational skills; increase youth participation in community activities.',
                    'target_groups' => 'School-age children, young people and community educators.',
                    'seo_title' => 'Education and youth programme',
                    'seo_description' => 'A planned programme for learning spaces and supplementary education.',
                ],
            ],
            [
                'code' => 'PRG-006',
                'slug' => 'community-participation-programme',
                'pillar' => '08',
                'manager' => 'omar.faisal@pcdr.test',
                'unit' => 'وحدة المشاركة المجتمعية',
                'category' => 'community_participation',
                'proposed_budget' => '180000.00',
                'approved_budget' => '172000.00',
                'start_date' => '2026-06-01',
                'end_date' => '2026-12-01',
                'media' => 'tree-planting',
                'order' => 60,
                'ar' => [
                    'title' => 'برنامج المشاركة المجتمعية والتطوع',
                    'summary' => 'برنامج مخطط لتنظيم الفرق التطوعية وتحسين قنوات المشاركة المحلية.',
                    'description' => 'يركز البرنامج على تشكيل فرق مجتمعية وتوزيع المهام وتوثيق الجداول الزمنية، مع استخدام ملاحظات المشاركين لتحسين التخطيط وتقليل الازدواج في الجهود.',
                    'objectives' => 'تنظيم الفرق التطوعية؛ تحسين قنوات الملاحظات؛ تعزيز التخطيط التشاركي.',
                    'target_groups' => 'المتطوعون، وممثلو الأحياء، واللجان المجتمعية.',
                    'seo_title' => 'برنامج المشاركة المجتمعية',
                    'seo_description' => 'برنامج مخطط لتنظيم الفرق التطوعية وتحسين المشاركة المحلية.',
                ],
                'en' => [
                    'title' => 'Community participation and volunteering programme',
                    'summary' => 'A planned programme organising volunteer teams and improving local participation channels.',
                    'description' => 'The programme focuses on forming community teams, assigning tasks and documenting schedules, while using participant feedback to improve planning and reduce duplication.',
                    'objectives' => 'Organise volunteer teams; improve feedback channels; strengthen participatory planning.',
                    'target_groups' => 'Volunteers, neighbourhood representatives and community committees.',
                    'seo_title' => 'Community participation programme',
                    'seo_description' => 'A planned programme organising volunteer teams and local participation.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function projectDefinitions(): array
    {
        return [
            [
                'code' => 'PRJ-001',
                'slug' => 'food-parcel-distribution-plan',
                'program' => 'PRG-001',
                'pillar' => '02',
                'manager' => 'sami.haddad@pcdr.test',
                'team' => 'وحدة الأمن الغذائي',
                'proposed_budget' => '168000.00',
                'approved_budget' => '165000.00',
                'start_date' => '2026-01-20',
                'end_date' => '2026-09-30',
                'actual_start_date' => '2026-02-01',
                'actual_end_date' => null,
                'media' => 'food-donations',
                'status' => 'ongoing',
                'order' => 10,
                'ar' => [
                    'title' => 'خطة توزيع الطرود الغذائية',
                    'summary' => 'مسار مخطط لتوريد الطرود الغذائية وتخزينها وتوزيعها وفق معايير معلنة.',
                    'description' => 'يركز المشروع على جرد الاحتياجات، وتنسيق التوريد، ومراجعة جودة الطرود قبل التسليم. تُسجّل كل جولة في نماذج موحدة لدعم المتابعة وتقليل الازدواج.',
                    'objectives' => 'تحسين انتظام وصول المواد الغذائية الأساسية؛ توثيق خطوات التوزيع؛ مراجعة ملاءمة الطرود للظروف المحلية.',
                    'target_groups' => 'الأسر محدودة الدخل ومقدمو الرعاية والفرق المجتمعية المشاركة في التوزيع.',
                    'seo_title' => 'خطة توزيع الطرود الغذائية',
                    'seo_description' => 'مسار مخطط لتوريد الطرود الغذائية وتخزينها وتوزيعها وفق معايير معلنة.',
                ],
                'en' => [
                    'title' => 'Food parcel distribution plan',
                    'summary' => 'A planned pathway for procuring, storing and distributing food parcels against published criteria.',
                    'description' => 'The project focuses on needs listing, procurement coordination and quality review before handover. Each round is recorded in standard forms to support tracking and reduce duplication.',
                    'objectives' => 'Improve regular access to staple food; document distribution steps; review parcel suitability for local conditions.',
                    'target_groups' => 'Low-income households, caregivers and community teams involved in distribution.',
                    'seo_title' => 'Food parcel distribution plan',
                    'seo_description' => 'A planned pathway for procuring, storing and distributing food parcels against published criteria.',
                ],
            ],
            [
                'code' => 'PRJ-002',
                'slug' => 'community-kitchens',
                'program' => 'PRG-001',
                'pillar' => '02',
                'manager' => 'nadia.qamar@pcdr.test',
                'team' => 'وحدة التغذية المجتمعية',
                'proposed_budget' => '114000.00',
                'approved_budget' => '112000.00',
                'start_date' => '2026-01-05',
                'end_date' => '2026-05-31',
                'actual_start_date' => '2026-01-10',
                'actual_end_date' => '2026-05-28',
                'media' => 'food-packing',
                'status' => 'completed',
                'order' => 20,
                'ar' => [
                    'title' => 'مطابخ مجتمعية للتغذية',
                    'summary' => 'تنظيم تجهيز الوجبات داخل مساحات مجتمعية محددة وتوثيق معايير الاستحقاق.',
                    'description' => 'عمل المشروع على تجهيز مساحات الطهو وتنسيق جدول الوجبات وتسجيل إجراءات الاستلام. اعتمدت المراجعة النهائية على استمارات الجودة وملاحظات الفرق المحلية.',
                    'objectives' => 'تنظيم توفير الوجبات في نطاق محدد؛ توحيد استمارات الاستحقاق؛ توثيق إجراءات الجودة.',
                    'target_groups' => 'الأسر التي تعتمد على وجبات مجتمعية والفرق التطوعية التي تدير المطابخ.',
                    'seo_title' => 'مطابخ مجتمعية للتغذية',
                    'seo_description' => 'تنظيم تجهيز الوجبات داخل مساحات مجتمعية وتوثيق معايير الاستحقاق.',
                ],
                'en' => [
                    'title' => 'Community kitchens for nutrition',
                    'summary' => 'Organising meal preparation in designated community spaces and documenting eligibility criteria.',
                    'description' => 'The project prepared cooking spaces, coordinated a meal schedule and recorded collection procedures. The final review drew on quality forms and feedback from local teams.',
                    'objectives' => 'Organise meals within a defined area; standardise eligibility forms; document quality procedures.',
                    'target_groups' => 'Households relying on community meals and volunteer teams running the kitchens.',
                    'seo_title' => 'Community kitchens for nutrition',
                    'seo_description' => 'Organising meal preparation in community spaces and documenting eligibility criteria.',
                ],
            ],
            [
                'code' => 'PRJ-003',
                'slug' => 'home-gardens',
                'program' => 'PRG-002',
                'pillar' => '03',
                'manager' => 'layla.nasser@pcdr.test',
                'team' => 'وحدة سبل العيش',
                'proposed_budget' => '90000.00',
                'approved_budget' => '88000.00',
                'start_date' => '2026-03-01',
                'end_date' => '2026-11-15',
                'actual_start_date' => '2026-03-18',
                'actual_end_date' => null,
                'media' => 'community-garden',
                'status' => 'ongoing',
                'order' => 30,
                'ar' => [
                    'title' => 'الحدائق المنزلية',
                    'summary' => 'دعم تخطيط مساحات زراعية صغيرة داخل الأحياء وتنظيم ورش تعليمية مرتبطة بها.',
                    'description' => 'يشمل المشروع اختيار المواقع المناسبة، وتوزيع أدوات بسيطة، وعقد ورش حول الري والشتلات. تُراجع الخطط الفردية بشكل دوري لتحديد احتياجات المتابعة.',
                    'objectives' => 'تخطيط حدائق منزلية صغيرة؛ تحسين ممارسات الري والعناية بالشتلات؛ توثيق ورش التدريب.',
                    'target_groups' => 'الأسر المنتجة وهواة البستنة في المناطق الحضرية.',
                    'seo_title' => 'الحدائق المنزلية',
                    'seo_description' => 'دعم تخطيط مساحات زراعية صغيرة وتنظيم ورش تعليمية مرتبطة بها.',
                ],
                'en' => [
                    'title' => 'Home gardens',
                    'summary' => 'Supporting the planning of small neighbourhood growing spaces and related workshops.',
                    'description' => 'The project includes site selection, distribution of simple tools and workshops on irrigation and seedlings. Individual plans are reviewed periodically to identify follow-up needs.',
                    'objectives' => 'Plan small home gardens; improve irrigation and seedling care practices; document training workshops.',
                    'target_groups' => 'Home-based producers and urban gardening enthusiasts.',
                    'seo_title' => 'Home gardens',
                    'seo_description' => 'Supporting small growing spaces and related educational workshops.',
                ],
            ],
            [
                'code' => 'PRJ-004',
                'slug' => 'vocational-training-pathway',
                'program' => 'PRG-002',
                'pillar' => '03',
                'manager' => 'omar.faisal@pcdr.test',
                'team' => 'وحدة التدريب المهني',
                'proposed_budget' => '128000.00',
                'approved_budget' => '125000.00',
                'start_date' => '2026-03-10',
                'end_date' => '2026-10-31',
                'actual_start_date' => '2026-04-02',
                'actual_end_date' => null,
                'media' => 'lecture',
                'status' => 'ongoing',
                'order' => 40,
                'ar' => [
                    'title' => 'مسار التدريب المهني للأسر المنتجة',
                    'summary' => 'ورش عملية في مهن منزلية مع خطط عمل فردية ومتابعة دورية.',
                    'description' => 'يبدأ المشروع بتقييم المهارات ثم ينتقل إلى ورش تطبيقية وخطط عمل فردية. تُستخدم استمارات متابعة بسيطة لرصد انتظام الأنشطة وتحديد احتياجات التدريب الإضافي.',
                    'objectives' => 'تطوير مهارات عملية؛ إعداد خطط عمل فردية؛ تحسين توثيق المشاركة.',
                    'target_groups' => 'الأسر المنتجة والخريجون غير العاملين وأصحاب الحرف الصغيرة.',
                    'seo_title' => 'مسار التدريب المهني',
                    'seo_description' => 'ورش عملية في مهن منزلية مع خطط عمل فردية ومتابعة دورية.',
                ],
                'en' => [
                    'title' => 'Vocational training pathway for home-based producers',
                    'summary' => 'Practical workshops in home-based trades with individual action plans and periodic follow-up.',
                    'description' => 'The project begins with a skills assessment and moves into applied workshops and individual action plans. Simple follow-up forms track the regularity of activities and identify extra training needs.',
                    'objectives' => 'Develop practical skills; prepare individual action plans; improve participation records.',
                    'target_groups' => 'Home-based producers, unemployed graduates and small-scale craftspeople.',
                    'seo_title' => 'Vocational training pathway',
                    'seo_description' => 'Practical workshops in home-based trades with individual plans and follow-up.',
                ],
            ],
            [
                'code' => 'PRJ-005',
                'slug' => 'psychosocial-support-groups',
                'program' => 'PRG-003',
                'pillar' => '04',
                'manager' => 'karim.zuhair@pcdr.test',
                'team' => 'وحدة الدعم النفسي',
                'proposed_budget' => '86000.00',
                'approved_budget' => '84000.00',
                'start_date' => '2026-04-01',
                'end_date' => '2026-12-15',
                'actual_start_date' => null,
                'actual_end_date' => null,
                'media' => 'adult-education',
                'status' => 'planned',
                'order' => 50,
                'ar' => [
                    'title' => 'جلسات الدعم النفسي والاجتماعي',
                    'summary' => 'تخطيط جلسات منظمة للبالغين والأطفال مع مراعاة الخصوصية وحماية الطفل.',
                    'description' => 'يعد المشروع إطاراً لجلسات توعية وأنشطة اجتماعية آمنة، مع تدريب الفرق على آليات الإحالة وتسجيل الملاحظات دون تعريف المشاركين. يعتمد التنفيذ على موافقات الأوصياء حيث يلزم ذلك.',
                    'objectives' => 'توفير مساحات آمنة للأنشطة؛ تدريب الفرق على الحماية والخصوصية؛ تنظيم آليات الإحالة.',
                    'target_groups' => 'الأطفال والمراهقون ومقدمو الرعاية وفرق الدعم المجتمعي.',
                    'seo_title' => 'جلسات الدعم النفسي والاجتماعي',
                    'seo_description' => 'تخطيط جلسات منظمة مع مراعاة الخصوصية وحماية الطفل.',
                ],
                'en' => [
                    'title' => 'Psychosocial support groups',
                    'summary' => 'Planning structured sessions for adults and children with privacy and child-protection safeguards.',
                    'description' => 'The project prepares a framework for awareness sessions and safe social activities, training teams on referral mechanisms and note-taking without identifying participants. Implementation relies on guardian consent where required.',
                    'objectives' => 'Provide safe spaces for activities; train teams on protection and privacy; organise referral mechanisms.',
                    'target_groups' => 'Children, adolescents, caregivers and community support teams.',
                    'seo_title' => 'Psychosocial support groups',
                    'seo_description' => 'Planning structured sessions with privacy and child-protection safeguards.',
                ],
            ],
            [
                'code' => 'PRJ-006',
                'slug' => 'water-point-maintenance',
                'program' => 'PRG-004',
                'pillar' => '05',
                'manager' => 'reem.adwan@pcdr.test',
                'team' => 'وحدة المياه والإصحاح',
                'proposed_budget' => '102000.00',
                'approved_budget' => '100000.00',
                'start_date' => '2026-04-10',
                'end_date' => '2026-10-20',
                'actual_start_date' => '2026-04-25',
                'actual_end_date' => null,
                'media' => 'water-well',
                'status' => 'ongoing',
                'order' => 60,
                'ar' => [
                    'title' => 'صيانة مصادر المياه',
                    'summary' => 'جرد نقاط الماء وتحديد أعمال الصيانة البسيطة وتوثيقها حسب الأولوية.',
                    'description' => 'يشمل المشروع زيارات جرد منتظمة وتسجيل حالة نقاط الماء وتنسيق أعمال الصيانة البسيطة مع الفرق المختصة. تُرفق التقارير بصور غير تعريفية وبيانات موقع محدودة.',
                    'objectives' => 'تحسين موثوقية نقاط الماء؛ توثيق أعمال الصيانة؛ نشر ممارسات الاستخدام الآمن.',
                    'target_groups' => 'الأسر في المناطق المخدمة وفرق صيانة المياه والمدارس المجتمعية.',
                    'seo_title' => 'صيانة مصادر المياه',
                    'seo_description' => 'جرد نقاط الماء وتحديد أعمال الصيانة البسيطة وتوثيقها حسب الأولوية.',
                ],
                'en' => [
                    'title' => 'Water point maintenance',
                    'summary' => 'Inventorying water points, identifying simple repairs and documenting them by priority.',
                    'description' => 'The project includes regular inventory visits, recording the condition of water points and coordinating simple maintenance with specialist teams. Reports use non-identifying images and limited location data.',
                    'objectives' => 'Improve water-point reliability; document maintenance; promote safe-use practices.',
                    'target_groups' => 'Households in served areas, water maintenance teams and community schools.',
                    'seo_title' => 'Water point maintenance',
                    'seo_description' => 'Inventorying water points and documenting simple repairs by priority.',
                ],
            ],
            [
                'code' => 'PRJ-007',
                'slug' => 'temporary-learning-spaces',
                'program' => 'PRG-005',
                'pillar' => '06',
                'manager' => 'hana.rafiq@pcdr.test',
                'team' => 'وحدة التعليم والشباب',
                'proposed_budget' => '72000.00',
                'approved_budget' => '70000.00',
                'start_date' => '2026-05-01',
                'end_date' => '2026-12-10',
                'actual_start_date' => null,
                'actual_end_date' => null,
                'media' => 'classroom',
                'status' => 'planned',
                'order' => 70,
                'ar' => [
                    'title' => 'تجهيز مساحات تعلم مؤقتة',
                    'summary' => 'تخطيط تجهيز مساحات تعلم بالمستلزمات الأساسية وجدول أنشطة مكمل.',
                    'description' => 'يستعد المشروع لتجهيز مساحات صغيرة بمفردات وأدوات بسيطة، مع إعداد جدول جلسات دعم تعليمي وأنشطة ثقافية. تعتمد الموافقة على السلامة وإمكانية الوصول قبل التنفيذ.',
                    'objectives' => 'تجهيز مساحات تعلم مناسبة؛ دعم المهارات الأساسية؛ تنظيم مشاركة الشباب.',
                    'target_groups' => 'الأطفال في سن المدرسة والشباب ومعلمو المجتمع.',
                    'seo_title' => 'تجهيز مساحات تعلم مؤقتة',
                    'seo_description' => 'تخطيط تجهيز مساحات تعلم بالمستلزمات الأساسية وجدول أنشطة مكمل.',
                ],
                'en' => [
                    'title' => 'Temporary learning spaces',
                    'summary' => 'Planning learning spaces with essential supplies and a supplementary activity schedule.',
                    'description' => 'The project prepares small spaces with basic furniture and tools, along with a schedule for educational support and cultural activities. Safety and accessibility approvals are required before delivery.',
                    'objectives' => 'Prepare suitable learning spaces; support foundational skills; organise youth participation.',
                    'target_groups' => 'School-age children, young people and community educators.',
                    'seo_title' => 'Temporary learning spaces',
                    'seo_description' => 'Planning learning spaces with essential supplies and a supplementary schedule.',
                ],
            ],
            [
                'code' => 'PRJ-008',
                'slug' => 'neighbourhood-volunteer-committees',
                'program' => 'PRG-006',
                'pillar' => '08',
                'manager' => 'tareq.shukri@pcdr.test',
                'team' => 'وحدة المشاركة المجتمعية',
                'proposed_budget' => '58000.00',
                'approved_budget' => '56000.00',
                'start_date' => '2026-06-01',
                'end_date' => '2026-09-30',
                'actual_start_date' => '2026-06-08',
                'actual_end_date' => '2026-09-24',
                'media' => 'tree-planting',
                'status' => 'completed',
                'order' => 80,
                'ar' => [
                    'title' => 'لجان التطوع في الأحياء',
                    'summary' => 'تنظيم فرق تطوعية محلية وتوزيع المهام وتوثيق جداول العمل.',
                    'description' => 'عمل المشروع على تشكيل لجان صغيرة في الأحياء وتحديد مهام واضحة لكل فريق، مع استخدام ملاحظات المشاركين لتحسين الجداول وتجنب تكرار الجهود. اختتم بمراجعة إجراءات العمل.',
                    'objectives' => 'تنظيم الفرق التطوعية؛ تحسين قنوات الملاحظات؛ تعزيز التخطيط التشاركي.',
                    'target_groups' => 'المتطوعون وممثلو الأحياء واللجان المجتمعية.',
                    'seo_title' => 'لجان التطوع في الأحياء',
                    'seo_description' => 'تنظيم فرق تطوعية محلية وتوزيع المهام وتوثيق جداول العمل.',
                ],
                'en' => [
                    'title' => 'Neighbourhood volunteer committees',
                    'summary' => 'Organising local volunteer teams, assigning tasks and documenting work schedules.',
                    'description' => 'The project formed small neighbourhood committees with clear team responsibilities and used participant feedback to improve schedules and avoid duplicated efforts. It concluded with a review of working procedures.',
                    'objectives' => 'Organise volunteer teams; improve feedback channels; strengthen participatory planning.',
                    'target_groups' => 'Volunteers, neighbourhood representatives and community committees.',
                    'seo_title' => 'Neighbourhood volunteer committees',
                    'seo_description' => 'Organising local volunteer teams, tasks and documented work schedules.',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articleCategoryDefinitions(): array
    {
        return [
            [
                'slug' => 'field-updates',
                'order' => 10,
                'ar' => [
                    'name' => 'تحديثات الميدان',
                    'description' => 'متابعات مختصرة لمراحل التنفيذ والأنشطة الجارية في المناطق المخدمة.',
                ],
                'en' => [
                    'name' => 'Field updates',
                    'description' => 'Brief updates on delivery stages and activities in served areas.',
                ],
            ],
            [
                'slug' => 'food-security',
                'order' => 20,
                'ar' => [
                    'name' => 'الأمن الغذائي',
                    'description' => 'ملاحظات عن التوريد والتخزين والتوزيع وممارسات التغذية المجتمعية.',
                ],
                'en' => [
                    'name' => 'Food security',
                    'description' => 'Notes on procurement, storage, distribution and community nutrition practices.',
                ],
            ],
            [
                'slug' => 'livelihoods',
                'order' => 30,
                'ar' => [
                    'name' => 'سبل العيش',
                    'description' => 'ورش ومسارات مهنية تدعم الأنشطة الاقتصادية على مستوى الأسرة.',
                ],
                'en' => [
                    'name' => 'Livelihoods',
                    'description' => 'Workshops and vocational pathways supporting household-level economic activities.',
                ],
            ],
            [
                'slug' => 'community',
                'order' => 40,
                'ar' => [
                    'name' => 'المجتمع والمشاركة',
                    'description' => 'أخبار الفرق التطوعية واللجان المحلية وقنوات المشاركة المجتمعية.',
                ],
                'en' => [
                    'name' => 'Community and participation',
                    'description' => 'News about volunteer teams, local committees and community participation channels.',
                ],
            ],
        ];
    }

    /**
     * @return list<array{slug: string, ar: string, en: string}>
     */
    private function tagDefinitions(): array
    {
        return [
            ['slug' => 'food-security', 'ar' => 'الأمن الغذائي', 'en' => 'Food security'],
            ['slug' => 'livelihoods', 'ar' => 'سبل العيش', 'en' => 'Livelihoods'],
            ['slug' => 'wash', 'ar' => 'المياه والإصحاح', 'en' => 'Water and sanitation'],
            ['slug' => 'education', 'ar' => 'التعليم', 'en' => 'Education'],
            ['slug' => 'psychosocial-support', 'ar' => 'الدعم النفسي', 'en' => 'Psychosocial support'],
            ['slug' => 'volunteerism', 'ar' => 'التطوع', 'en' => 'Volunteering'],
            ['slug' => 'transparency', 'ar' => 'الشفافية', 'en' => 'Transparency'],
            ['slug' => 'community-participation', 'ar' => 'المشاركة المجتمعية', 'en' => 'Community participation'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articleDefinitions(): array
    {
        return [
            [
                'code' => 'ART-001',
                'slug' => 'food-parcel-plan-rollout',
                'category' => 'field-updates',
                'pillar' => '02',
                'author' => 'sami.haddad@pcdr.test',
                'event_date' => '2026-02-02',
                'featured' => true,
                'media' => 'food-donations',
                'order' => 10,
                'ar' => [
                    'title' => 'انطلاق خطة توزيع الطرود الغذائية',
                    'excerpt' => 'تخطيط التوريد والتخزين والتوزيع وفق معايير معلنة.',
                    'body' => 'بدأت خطة توزيع الطرود الغذائية بجرد الاحتياجات وتنسيق عمليات التوريد، مع توثيق كل جولة في استمارات موحدة. تهدف الخطة إلى تحسين انتظام وصول المواد الأساسية وتقليل الازدواج في الجهود الميدانية.',
                    'seo_title' => 'انطلاق خطة توزيع الطرود الغذائية',
                ],
                'en' => [
                    'title' => 'Food parcel distribution plan begins',
                    'excerpt' => 'Procurement, storage and distribution are planned against published criteria.',
                    'body' => 'The food parcel plan began with a needs listing and coordinated procurement, with each round recorded in standard forms. It aims to improve regular access to staple items and reduce duplication in field efforts.',
                    'seo_title' => 'Food parcel distribution plan begins',
                ],
            ],
            [
                'code' => 'ART-002',
                'slug' => 'community-kitchen-standards',
                'category' => 'food-security',
                'pillar' => '02',
                'author' => 'nadia.qamar@pcdr.test',
                'event_date' => '2026-02-20',
                'featured' => true,
                'media' => 'food-packing',
                'order' => 20,
                'ar' => [
                    'title' => 'معايير العمل في المطابخ المجتمعية',
                    'excerpt' => 'توثيق الاستحقاق والجودة ضمن مسار تشغيل واضح.',
                    'body' => 'اعتمدت الفرق العاملة في المطابخ المجتمعية استمارات موحدة لتسجيل الاستحقاق ودرجات الجودة. يساعد هذا المسار على تنظيم الوجبات وتوفير سجل قابل للمراجعة لكل مرحلة.',
                    'seo_title' => 'معايير العمل في المطابخ المجتمعية',
                ],
                'en' => [
                    'title' => 'Community kitchen working standards',
                    'excerpt' => 'Eligibility and quality are documented through a clear operating pathway.',
                    'body' => 'Teams working in community kitchens adopted standard forms for recording eligibility and quality checks. The pathway organises meal preparation and provides a reviewable record for each stage.',
                    'seo_title' => 'Community kitchen working standards',
                ],
            ],
            [
                'code' => 'ART-003',
                'slug' => 'home-garden-planning',
                'category' => 'food-security',
                'pillar' => '03',
                'author' => 'layla.nasser@pcdr.test',
                'event_date' => '2026-03-12',
                'featured' => false,
                'media' => 'community-garden',
                'order' => 30,
                'ar' => [
                    'title' => 'تخطيط الحدائق المنزلية في الأحياء',
                    'excerpt' => 'اختيار المواقع وتوزيع أدوات بسيطة وعقد ورش للري والعناية بالشتلات.',
                    'body' => 'تستمر ورش الحدائق المنزلية باختيار المواقع المتاحة وتوزيع أدوات بسيطة وشرح أساسيات الري والعناية بالشتلات. تعتمد المتابعة على خطط فردية قصيرة تراجعها الفرق المحلية بانتظام.',
                    'seo_title' => 'تخطيط الحدائق المنزلية في الأحياء',
                ],
                'en' => [
                    'title' => 'Planning home gardens in neighbourhoods',
                    'excerpt' => 'Site choice, simple tools and workshops on irrigation and seedling care.',
                    'body' => 'Home garden workshops focus on choosing available sites, distributing simple tools and explaining irrigation and seedling care. Follow-up uses short individual plans reviewed regularly by local teams.',
                    'seo_title' => 'Planning home gardens in neighbourhoods',
                ],
            ],
            [
                'code' => 'ART-004',
                'slug' => 'vocational-workshops-agenda',
                'category' => 'livelihoods',
                'pillar' => '03',
                'author' => 'omar.faisal@pcdr.test',
                'event_date' => '2026-04-04',
                'featured' => false,
                'media' => 'lecture',
                'order' => 40,
                'ar' => [
                    'title' => 'جدول الورش المهنية للأسر المنتجة',
                    'excerpt' => 'تقييم المهارات يسبق الورش التطبيقية وخطط العمل الفردية.',
                    'body' => 'يبدأ المسار المهني بتقييم بسيط للمهارات، ثم ورش تطبيقية في مهن منزلية مختارة. تُرفق كل ورشة بخطة عمل فردية واستمارة متابعة لتحديد احتياجات التدريب الإضافي.',
                    'seo_title' => 'جدول الورش المهنية للأسر المنتجة',
                ],
                'en' => [
                    'title' => 'Vocational workshop schedule for home producers',
                    'excerpt' => 'A skills assessment comes before applied workshops and individual plans.',
                    'body' => 'The vocational pathway starts with a simple skills assessment followed by applied workshops in selected home-based trades. Each workshop includes an individual action plan and a follow-up form to identify further training needs.',
                    'seo_title' => 'Vocational workshop schedule for home producers',
                ],
            ],
            [
                'code' => 'ART-005',
                'slug' => 'psychosocial-session-framework',
                'category' => 'field-updates',
                'pillar' => '04',
                'author' => 'karim.zuhair@pcdr.test',
                'event_date' => '2026-04-18',
                'featured' => false,
                'media' => 'adult-education',
                'order' => 50,
                'ar' => [
                    'title' => 'إطار جلسات الدعم النفسي والاجتماعي',
                    'excerpt' => 'مساحات آمنة وخصوصية وآليات إحالة واضحة.',
                    'body' => 'يجري إعداد إطار للجلسات يراعي حماية الطفل والخصوصية، ويشمل توعية الفرق وطرق تسجيل الملاحظات دون تعريف المشاركين. تعتمد الجلسات على موافقة الأوصياء حيث يلزم ذلك.',
                    'seo_title' => 'إطار جلسات الدعم النفسي والاجتماعي',
                ],
                'en' => [
                    'title' => 'Psychosocial support session framework',
                    'excerpt' => 'Safe spaces, privacy and clear referral mechanisms.',
                    'body' => 'A session framework is being prepared with child-protection and privacy safeguards, team orientation and note-taking methods that avoid identifying participants. Guardian consent is required where applicable.',
                    'seo_title' => 'Psychosocial support session framework',
                ],
            ],
            [
                'code' => 'ART-006',
                'slug' => 'water-point-inventory',
                'category' => 'field-updates',
                'pillar' => '05',
                'author' => 'reem.adwan@pcdr.test',
                'event_date' => '2026-05-02',
                'featured' => false,
                'media' => 'water-well',
                'order' => 60,
                'ar' => [
                    'title' => 'جرد نقاط الماء في المناطق المخدمة',
                    'excerpt' => 'تسجيل الحالة وتحديد أعمال الصيانة البسيطة حسب الأولوية.',
                    'body' => 'تسجل زيارات الجرد حالة نقاط الماء وتحدد أعمال الصيانة البسيطة المطلوبة. تُرفق التقارير ببيانات موقع محدودة وصور غير تعريفية، وتُرتب الأولويات وفق الاحتياج المعلن.',
                    'seo_title' => 'جرد نقاط الماء في المناطق المخدمة',
                ],
                'en' => [
                    'title' => 'Water point inventory in served areas',
                    'excerpt' => 'Condition records and simple repairs are prioritised transparently.',
                    'body' => 'Inventory visits record the condition of water points and identify simple maintenance needs. Reports use limited location data and non-identifying images, with priorities ordered against published criteria.',
                    'seo_title' => 'Water point inventory in served areas',
                ],
            ],
            [
                'code' => 'ART-007',
                'slug' => 'hygiene-awareness-sessions',
                'category' => 'field-updates',
                'pillar' => '05',
                'author' => 'reem.adwan@pcdr.test',
                'event_date' => '2026-05-22',
                'featured' => false,
                'media' => 'handwashing',
                'order' => 70,
                'ar' => [
                    'title' => 'جلسات توعية حول ممارسات النظافة',
                    'excerpt' => 'شرح غسل اليدين والاستخدام الآمن للمواد المنظفة.',
                    'body' => 'تتناول الجلسات خطوات غسل اليدين وتخزين المواد المنظفة بطريقة آمنة، مع توزيع ملخصات بسيطة بلغة واضحة. تُسجل عدد الجلسات دون جمع بيانات شخصية من المشاركين.',
                    'seo_title' => 'جلسات توعية حول ممارسات النظافة',
                ],
                'en' => [
                    'title' => 'Hygiene awareness sessions',
                    'excerpt' => 'Handwashing steps and safe storage of cleaning materials.',
                    'body' => 'The sessions cover handwashing steps and safe storage of cleaning materials, with short handouts in clear language. Session counts are recorded without collecting personal data from participants.',
                    'seo_title' => 'Hygiene awareness sessions',
                ],
            ],
            [
                'code' => 'ART-008',
                'slug' => 'learning-space-supplies',
                'category' => 'field-updates',
                'pillar' => '06',
                'author' => 'hana.rafiq@pcdr.test',
                'event_date' => '2026-06-05',
                'featured' => false,
                'media' => 'classroom',
                'order' => 80,
                'ar' => [
                    'title' => 'تجهيز مساحات تعلم بالمستلزمات الأساسية',
                    'excerpt' => 'أثاث بسيط وأدوات تعليمية وجدول أنشطة مكمل.',
                    'body' => 'تُجهز مساحات التعلم بأثاث بسيط وأدوات تعليمية، ويُرتب جدول جلسات دعم وأنشطة ثقافية مناسبة للأعمار المختلفة. تخضع المساحات لمراجعة السلامة وإمكانية الوصول قبل الاستخدام.',
                    'seo_title' => 'تجهيز مساحات تعلم بالمستلزمات الأساسية',
                ],
                'en' => [
                    'title' => 'Preparing learning spaces with essential supplies',
                    'excerpt' => 'Simple furniture, learning tools and a supplementary activity schedule.',
                    'body' => 'Learning spaces are being prepared with simple furniture and educational tools, alongside a schedule of support sessions and age-appropriate cultural activities. Safety and accessibility reviews precede use.',
                    'seo_title' => 'Preparing learning spaces with essential supplies',
                ],
            ],
            [
                'code' => 'ART-009',
                'slug' => 'volunteer-team-schedules',
                'category' => 'community',
                'pillar' => '08',
                'author' => 'tareq.shukri@pcdr.test',
                'event_date' => '2026-06-19',
                'featured' => false,
                'media' => 'tree-planting',
                'order' => 90,
                'ar' => [
                    'title' => 'جداول عمل الفرق التطوعية',
                    'excerpt' => 'توزيع المهام وتوثيق الجداول لتجنب تكرار الجهود.',
                    'body' => 'تُوزّع المهام على فرق تطوعية صغيرة وفق مجالات واضحة، وتُسجّل الجداول في نموذج واحد يتيح للجان المحلية مراجعة التغطية وتعديلها عند الحاجة.',
                    'seo_title' => 'جداول عمل الفرق التطوعية',
                ],
                'en' => [
                    'title' => 'Volunteer team work schedules',
                    'excerpt' => 'Tasks and schedules are recorded to avoid duplicated effort.',
                    'body' => 'Tasks are assigned to small volunteer teams across clear focus areas and recorded in one schedule template that allows local committees to review coverage and adjust it as needed.',
                    'seo_title' => 'Volunteer team work schedules',
                ],
            ],
            [
                'code' => 'ART-010',
                'slug' => 'field-coordination-notes',
                'category' => 'community',
                'pillar' => '08',
                'author' => 'omar.faisal@pcdr.test',
                'event_date' => '2026-07-03',
                'featured' => false,
                'media' => 'food-handover',
                'order' => 100,
                'ar' => [
                    'title' => 'ملاحظات تنسيق ميداني',
                    'excerpt' => 'كيف تساعد الجداول المشتركة على تقليل الازدواج وتحسين المتابعة.',
                    'body' => 'تستخدم الفرق جدولاً مشتركاً لتسجيل الجولات والمهام والمواعيد، مع مراجعة أسبوعية قصيرة للتأكد من تغطية المواقع وتحديث الملاحظات. يعتمد التوثيق على وصف عام دون أسماء أفراد.',
                    'seo_title' => 'ملاحظات تنسيق ميداني',
                ],
                'en' => [
                    'title' => 'Field coordination notes',
                    'excerpt' => 'How shared schedules reduce duplication and improve follow-up.',
                    'body' => 'Teams use a shared schedule to record rounds, tasks and timings, followed by a short weekly review of coverage and notes. Documentation uses general descriptions without individual names.',
                    'seo_title' => 'Field coordination notes',
                ],
            ],
            [
                'code' => 'ART-011',
                'slug' => 'documenting-distribution-rounds',
                'category' => 'field-updates',
                'pillar' => '02',
                'author' => 'sami.haddad@pcdr.test',
                'event_date' => '2026-07-24',
                'featured' => false,
                'media' => 'donation-sorting',
                'order' => 110,
                'ar' => [
                    'title' => 'توثيق جولات التوزيع',
                    'excerpt' => 'استمارات موحدة ومراجعة جودة قبل التسليم.',
                    'body' => 'تُوثّق جولات التوزيع باستمارات موحدة تشمل الجرد ومراجعة الجودة والتسليم، مع حفظ الصور في مكتبة الوسائط الداخلية بعناوين عامة غير تعريفية.',
                    'seo_title' => 'توثيق جولات التوزيع',
                ],
                'en' => [
                    'title' => 'Documenting distribution rounds',
                    'excerpt' => 'Standard forms and quality review before handover.',
                    'body' => 'Distribution rounds are documented with standard forms covering counting, quality review and handover, while images are kept in the internal media library with non-identifying captions.',
                    'seo_title' => 'Documenting distribution rounds',
                ],
            ],
            [
                'code' => 'ART-012',
                'slug' => 'community-feedback-review',
                'category' => 'community',
                'pillar' => '08',
                'author' => 'nadia.qamar@pcdr.test',
                'event_date' => '2026-08-14',
                'featured' => false,
                'media' => 'community-garden',
                'order' => 120,
                'ar' => [
                    'title' => 'مراجعة ملاحظات المجتمع',
                    'excerpt' => 'تصنيف الملاحظات وتحويلها إلى تحسينات في التخطيط.',
                    'body' => 'تُراجع الملاحظات الواردة من القنوات المتاحة وتُصنّف حسب الموضوع، ثم تُدرج في جدول متابعة يوضح الإجراءات المقترحة والمسؤول عنها دون ربطها بأفراد محددين.',
                    'seo_title' => 'مراجعة ملاحظات المجتمع',
                ],
                'en' => [
                    'title' => 'Reviewing community feedback',
                    'excerpt' => 'Feedback is categorised and translated into planning improvements.',
                    'body' => 'Feedback received through available channels is reviewed and grouped by topic, then entered into a follow-up table that lists proposed actions and responsible roles without linking them to named individuals.',
                    'seo_title' => 'Reviewing community feedback',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicationDefinitions(): array
    {
        return [
            [
                'code' => 'PUB-001',
                'slug' => 'food-security-plan-2026',
                'category' => 'strategic_plan',
                'pillar' => '02',
                'publication_date' => '2026-01-30',
                'version' => '2026/1',
                'media' => 'food-donations',
                'order' => 10,
                'ar' => [
                    'title' => 'ملخص خطة الأمن الغذائي لعام 2026',
                    'description' => 'ملخص تخطيطي لمسارات التوريد والتخزين والتوزيع ومؤشرات المتابعة المقترحة.',
                    'seo_title' => 'خطة الأمن الغذائي 2026',
                ],
                'en' => [
                    'title' => 'Food security plan summary 2026',
                    'description' => 'A planning summary of procurement, storage and distribution pathways with proposed tracking indicators.',
                    'seo_title' => 'Food security plan 2026',
                ],
            ],
            [
                'code' => 'PUB-002',
                'slug' => 'water-and-hygiene-guidelines',
                'category' => 'policy',
                'pillar' => '05',
                'publication_date' => '2026-02-28',
                'version' => '1.0',
                'media' => 'handwashing',
                'order' => 20,
                'ar' => [
                    'title' => 'دليل النظافة وإدارة المياه',
                    'description' => 'إرشادات عملية لغسل اليدين وتخزين المواد المنظفة وصيانة نقاط الماء.',
                    'seo_title' => 'دليل النظافة وإدارة المياه',
                ],
                'en' => [
                    'title' => 'Water and hygiene guidelines',
                    'description' => 'Practical guidance on handwashing, cleaning-material storage and water-point maintenance.',
                    'seo_title' => 'Water and hygiene guidelines',
                ],
            ],
            [
                'code' => 'PUB-003',
                'slug' => 'livelihoods-training-framework',
                'category' => 'institutional',
                'pillar' => '03',
                'publication_date' => '2026-03-31',
                'version' => '1.0',
                'media' => 'farmer-harvest',
                'order' => 30,
                'ar' => [
                    'title' => 'إطار التدريب على سبل العيش',
                    'description' => 'هيكل ورش مهني مبسط يجمع تقييم المهارات وخطط العمل والمتابعة.',
                    'seo_title' => 'إطار التدريب على سبل العيش',
                ],
                'en' => [
                    'title' => 'Livelihoods training framework',
                    'description' => 'A simplified workshop structure combining skills assessment, action plans and follow-up.',
                    'seo_title' => 'Livelihoods training framework',
                ],
            ],
            [
                'code' => 'PUB-004',
                'slug' => 'psychosocial-support-notes',
                'category' => 'research',
                'pillar' => '04',
                'publication_date' => '2026-04-30',
                'version' => '1.0',
                'media' => 'adult-education',
                'order' => 40,
                'ar' => [
                    'title' => 'ملاحظات في الدعم النفسي المجتمعي',
                    'description' => 'مراجعة مبسطة لمبادئ الخصوصية وحماية الطفل وآليات الإحالة في الجلسات الجماعية.',
                    'seo_title' => 'ملاحظات في الدعم النفسي المجتمعي',
                ],
                'en' => [
                    'title' => 'Notes on community psychosocial support',
                    'description' => 'A concise review of privacy, child protection and referral principles in group sessions.',
                    'seo_title' => 'Notes on community psychosocial support',
                ],
            ],
            [
                'code' => 'PUB-005',
                'slug' => 'education-space-checklist',
                'category' => 'institutional',
                'pillar' => '06',
                'publication_date' => '2026-05-29',
                'version' => '1.1',
                'media' => 'classroom',
                'order' => 50,
                'ar' => [
                    'title' => 'قائمة فحص مساحات التعلم',
                    'description' => 'قائمة قصيرة للسلامة وإمكانية الوصول والمستلزمات الأساسية قبل بدء الأنشطة.',
                    'seo_title' => 'قائمة فحص مساحات التعلم',
                ],
                'en' => [
                    'title' => 'Learning space checklist',
                    'description' => 'A short checklist for safety, accessibility and essential supplies before activities begin.',
                    'seo_title' => 'Learning space checklist',
                ],
            ],
            [
                'code' => 'PUB-006',
                'slug' => 'volunteer-coordination-brief',
                'category' => 'institutional',
                'pillar' => '08',
                'publication_date' => '2026-06-30',
                'version' => '1.0',
                'media' => 'tree-planting',
                'order' => 60,
                'ar' => [
                    'title' => 'موجز تنسيق العمل التطوعي',
                    'description' => 'إجراءات بسيطة لتوزيع المهام وتسجيل الجداول وتنظيم قنوات الملاحظات.',
                    'seo_title' => 'موجز تنسيق العمل التطوعي',
                ],
                'en' => [
                    'title' => 'Volunteer coordination brief',
                    'description' => 'Simple procedures for assigning tasks, recording schedules and organising feedback channels.',
                    'seo_title' => 'Volunteer coordination brief',
                ],
            ],
            [
                'code' => 'PUB-007',
                'slug' => 'field-needs-assessment-notes',
                'category' => 'needs_assessment',
                'pillar' => '01',
                'publication_date' => '2026-07-31',
                'version' => '2026/2',
                'media' => 'health-check',
                'order' => 70,
                'ar' => [
                    'title' => 'ملاحظات تقييم الاحتياجات الميدانية',
                    'description' => 'منهج مبسط لجمع الملاحظات وتصنيفها دون تخزين بيانات تعريفية للأفراد.',
                    'seo_title' => 'ملاحظات تقييم الاحتياجات الميدانية',
                ],
                'en' => [
                    'title' => 'Field needs assessment notes',
                    'description' => 'A simplified approach to collecting and grouping observations without storing identifying details.',
                    'seo_title' => 'Field needs assessment notes',
                ],
            ],
            [
                'code' => 'PUB-008',
                'slug' => 'midyear-planning-review',
                'category' => 'annual_report',
                'pillar' => '01',
                'publication_date' => '2026-08-31',
                'version' => '2026/H1',
                'media' => 'olive-farming',
                'order' => 80,
                'ar' => [
                    'title' => 'مراجعة التخطيط منتصف العام',
                    'description' => 'مراجعة داخلية للجداول والمؤشرات التخطيطية لتحديد احتياجات التحديث.',
                    'seo_title' => 'مراجعة التخطيط منتصف العام',
                ],
                'en' => [
                    'title' => 'Midyear planning review',
                    'description' => 'An internal review of schedules and planning indicators to identify updates needed.',
                    'seo_title' => 'Midyear planning review',
                ],
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function articleTagPairs(): array
    {
        return [
            ['ART-001', 'food-security'],
            ['ART-001', 'transparency'],
            ['ART-002', 'food-security'],
            ['ART-002', 'transparency'],
            ['ART-003', 'food-security'],
            ['ART-003', 'livelihoods'],
            ['ART-004', 'livelihoods'],
            ['ART-004', 'education'],
            ['ART-005', 'psychosocial-support'],
            ['ART-005', 'transparency'],
            ['ART-006', 'wash'],
            ['ART-006', 'transparency'],
            ['ART-007', 'wash'],
            ['ART-007', 'education'],
            ['ART-008', 'education'],
            ['ART-009', 'volunteerism'],
            ['ART-009', 'community-participation'],
            ['ART-010', 'community-participation'],
            ['ART-011', 'food-security'],
            ['ART-012', 'community-participation'],
        ];
    }

    /**
     * @param  array<string, int>  $projectIds
     * @param  array<string, int>  $articleIds
     * @param  array<string, int>  $publicationIds
     * @return list<array<string, mixed>>
     */
    private function homepageItemDefinitions(array $projectIds, array $articleIds, array $publicationIds): array
    {
        $definitions = [
            ['news', 'project-1', 'project', 'PRJ-001', 'food-donations', 'projects', true, 20, 'food-parcel-distribution-plan'],
            ['news', 'article-1', 'article', 'ART-001', 'food-donations', 'news', true, 21, 'food-parcel-plan-rollout'],
            ['news', 'article-2', 'article', 'ART-002', 'food-packing', 'news', false, 22, 'community-kitchen-standards'],
            ['news', 'article-3', 'article', 'ART-003', 'community-garden', 'news', false, 23, 'home-garden-planning'],
            ['news', 'publication-1', 'publication', 'PUB-001', 'food-donations', 'publications', true, 24, 'food-security-plan-2026'],
            ['news', 'publication-2', 'publication', 'PUB-002', 'handwashing', 'publications', false, 25, 'water-and-hygiene-guidelines'],
            ['news', 'project-2', 'project', 'PRJ-002', 'food-packing', 'projects', false, 26, 'community-kitchens'],
            ['news', 'project-3', 'project', 'PRJ-003', 'community-garden', 'projects', false, 27, 'home-gardens'],
            ['news', 'article-4', 'article', 'ART-006', 'water-well', 'news', false, 28, 'water-point-inventory'],
            ['news', 'article-5', 'article', 'ART-009', 'tree-planting', 'news', false, 29, 'volunteer-team-schedules'],
            ['programs', 'project-4', 'project', 'PRJ-004', 'lecture', 'projects', true, 30, 'vocational-training-pathway'],
            ['programs', 'project-5', 'project', 'PRJ-005', 'adult-education', 'projects', false, 31, 'psychosocial-support-groups'],
            ['programs', 'project-6', 'project', 'PRJ-006', 'water-well', 'projects', false, 32, 'water-point-maintenance'],
            ['impact', 'project-7', 'project', 'PRJ-007', 'classroom', 'projects', false, 33, 'temporary-learning-spaces'],
            ['impact', 'publication-3', 'publication', 'PUB-007', 'health-check', 'publications', false, 34, 'field-needs-assessment-notes'],
        ];

        $ids = [
            'project' => $projectIds,
            'article' => $articleIds,
            'publication' => $publicationIds,
        ];

        $copy = [
            'PRJ-001' => ['ar' => ['توزيع الطرود الغذائية', 'خطة منظمة للتوريد والتوزيع'], 'en' => ['Food parcel distribution', 'An organised procurement and distribution plan']],
            'PRJ-002' => ['ar' => ['المطابخ المجتمعية', 'وجبات مجتمعية بمعايير موثقة'], 'en' => ['Community kitchens', 'Community meals with documented standards']],
            'PRJ-003' => ['ar' => ['الحدائق المنزلية', 'مساحات زراعية صغيرة في الأحياء'], 'en' => ['Home gardens', 'Small growing spaces in neighbourhoods']],
            'PRJ-004' => ['ar' => ['التدريب المهني', 'ورش عملية وخطط عمل فردية'], 'en' => ['Vocational training', 'Applied workshops and individual plans']],
            'PRJ-005' => ['ar' => ['الدعم النفسي والاجتماعي', 'جلسات آمنة تحترم الخصوصية'], 'en' => ['Psychosocial support', 'Safe sessions that respect privacy']],
            'PRJ-006' => ['ar' => ['صيانة مصادر المياه', 'جرد نقاط الماء وأعمال الصيانة'], 'en' => ['Water point maintenance', 'Water-point inventory and simple repairs']],
            'PRJ-007' => ['ar' => ['مساحات التعلم', 'تجهيز مساحات صغيرة بالمستلزمات'], 'en' => ['Learning spaces', 'Preparing small spaces with essential supplies']],
            'ART-001' => ['ar' => ['انطلاق خطة الطرود', 'بدء التوريد والتوزيع وفق معايير معلنة'], 'en' => ['Food parcel plan begins', 'Procurement and distribution start against published criteria']],
            'ART-002' => ['ar' => ['معايير المطابخ', 'استمارات موحدة للاستحقاق والجودة'], 'en' => ['Kitchen standards', 'Standard forms for eligibility and quality']],
            'ART-003' => ['ar' => ['الحدائق المنزلية', 'ورش الري والعناية بالشتلات'], 'en' => ['Home gardens', 'Workshops on irrigation and seedling care']],
            'ART-006' => ['ar' => ['جرد نقاط الماء', 'تسجيل الحالة وتحديد أولويات الصيانة'], 'en' => ['Water point inventory', 'Recording condition and maintenance priorities']],
            'ART-009' => ['ar' => ['جداول التطوع', 'توزيع المهام وتوثيق الجداول'], 'en' => ['Volunteer schedules', 'Assigning tasks and documenting schedules']],
            'PUB-001' => ['ar' => ['خطة الأمن الغذائي', 'ملخص تخطيطي لعام 2026'], 'en' => ['Food security plan', 'Planning summary for 2026']],
            'PUB-002' => ['ar' => ['دليل النظافة والمياه', 'إرشادات عملية للاستخدام الآمن'], 'en' => ['Water and hygiene guide', 'Practical guidance for safe use']],
            'PUB-007' => ['ar' => ['تقييم الاحتياجات', 'ملاحظات منهجية مبسطة'], 'en' => ['Needs assessment', 'A simplified methodological note']],
        ];

        $arabicTabs = [
            'projects' => 'مشاريع',
            'news' => 'أخبار',
            'publications' => 'إصدارات',
        ];
        $pathSegments = [
            'project' => 'projects',
            'article' => 'news',
            'publication' => 'publications',
        ];

        $result = [];

        foreach ($definitions as [$section, $itemKey, $itemType, $naturalKey, $media, $tab, $featured, $sortOrder, $slug]) {
            $labelSet = $copy[$naturalKey] ?? null;

            if ($labelSet === null) {
                continue;
            }

            $path = $pathSegments[$itemType] ?? 'projects';

            $result[] = [
                'section' => $section,
                'item_key' => $itemKey,
                'item_type' => $itemType,
                'item_id' => $ids[$itemType][$naturalKey] ?? null,
                'media' => $media,
                'tab' => $tab,
                'featured' => $featured,
                'sort_order' => $sortOrder,
                'ar' => [
                    'label' => $arabicTabs[$tab] ?? $tab,
                    'title' => $labelSet['ar'][0],
                    'body' => $labelSet['ar'][1],
                    'date_label' => '2026',
                    'cta_label' => 'التفاصيل',
                    'url' => '/ar/'.$path,
                    'caption' => $labelSet['ar'][1],
                ],
                'en' => [
                    'label' => $tab,
                    'title' => $labelSet['en'][0],
                    'body' => $labelSet['en'][1],
                    'date_label' => '2026',
                    'cta_label' => 'Details',
                    'url' => '/en/'.$path,
                    'caption' => $labelSet['en'][1],
                ],
            ];
        }

        return $result;
    }
}
