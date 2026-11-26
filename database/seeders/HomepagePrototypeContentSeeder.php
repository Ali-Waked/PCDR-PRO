<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use App\Services\HomepageService;
use Illuminate\Database\Seeder;

/**
 * Seeds the approved source-homepage prototype as nine CMS sections.
 *
 * Arabic copy is transcribed from docs/redesign/source-homepage/index.html.
 * English copy is a faithful translation of that Arabic source, not a separate
 * institutional claim. Placeholder links and photographs are never invented:
 * source anchors written as "#" are stored as null. Legacy section keys are
 * disabled, never deleted.
 */
final class HomepagePrototypeContentSeeder extends Seeder
{
    private const LOCALES = ['ar', 'en'];

    private const SECTION_KEYS = [
        'hero', 'ticker', 'impact', 'programs', 'news',
        'albums', 'about', 'stories', 'footer',
    ];

    /** @var list<string> */
    private const LEGACY_KEYS = [
        'hero', 'intro', 'areas', 'framework', 'programs',
        'targets', 'community', 'partners', 'knowledge', 'cta',
    ];

    public function run(): void
    {
        foreach ($this->sectionDefinitions() as $key => $definition) {
            $this->seedSection((string) $key, $definition);
        }

        foreach ($this->heroSlides() as $itemKey => $definition) {
            $this->seedItem('hero', (string) $itemKey, $definition);
        }

        foreach ($this->tickerHeadlines() as $itemKey => $definition) {
            $this->seedItem('ticker', (string) $itemKey, $definition);
        }

        foreach ($this->impactItems() as $itemKey => $definition) {
            $this->seedItem('impact', (string) $itemKey, $definition);
        }

        foreach ($this->programAxes() as $itemKey => $definition) {
            $this->seedItem('programs', (string) $itemKey, $definition);
        }

        foreach ($this->newsItems() as $itemKey => $definition) {
            $this->seedItem('news', (string) $itemKey, $definition);
        }

        foreach ($this->albumItems() as $itemKey => $definition) {
            $this->seedItem('albums', (string) $itemKey, $definition);
        }

        foreach ($this->aboutItems() as $itemKey => $definition) {
            $this->seedItem('about', (string) $itemKey, $definition);
        }

        foreach ($this->storyItems() as $itemKey => $definition) {
            $this->seedItem('stories', (string) $itemKey, $definition);
        }

        foreach ($this->footerItems() as $itemKey => $definition) {
            $this->seedItem('footer', (string) $itemKey, $definition);
        }

        HomepageSection::query()
            ->whereIn('key', array_values(array_diff(self::LEGACY_KEYS, self::SECTION_KEYS)))
            ->update(['is_enabled' => false]);

        app(HomepageService::class)->flush();
    }

    /**
     * @param  array{order: int, ar: array<string, mixed>, en: array<string, mixed>}  $definition
     */
    private function seedSection(string $key, array $definition): void
    {
        $section = HomepageSection::query()->firstOrNew(['key' => $key]);

        if (! $section->exists) {
            $section->fill([
                'type' => $key,
                'status' => 'published',
                'published_at' => now(),
                'is_enabled' => true,
                'display_order' => $definition['order'],
            ]);
            $section->save();
        } elseif ((int) $section->display_order !== $definition['order']) {
            $section->update(['display_order' => $definition['order']]);
        }

        foreach (self::LOCALES as $locale) {
            $translation = $section->translations()->firstOrNew(['locale' => $locale]);

            foreach ($definition[$locale] as $field => $value) {
                if (filled($translation->getAttribute($field)) || ! filled($value)) {
                    continue;
                }

                $translation->setAttribute($field, $value);
            }

            if ($translation->isDirty()) {
                $translation->save();
            }
        }
    }

    /**
     * @param  array{
     *     order: int,
     *     tab?: string|null,
     *     featured?: bool,
     *     motif?: string|null,
     *     value?: float,
     *     ar: array<string, string|null>,
     *     en: array<string, string|null>
     * }  $definition
     */
    private function seedItem(string $sectionKey, string $itemKey, array $definition): void
    {
        $section = HomepageSection::query()->where('key', $sectionKey)->firstOrFail();
        $metadata = [
            'tab' => $definition['tab'] ?? null,
            'is_featured' => $definition['featured'] ?? false,
            'motif_key' => $definition['motif'] ?? null,
            'sort_order' => $definition['order'],
        ];

        $item = $section->items()->firstOrCreate(
            ['item_key' => $itemKey],
            [
                'item_type' => 'inline',
                'item_id' => null,
                'media_id' => null,
                'is_published' => true,
                'numeric_value' => $definition['value'] ?? null,
                ...$metadata,
            ],
        );

        $item->update($metadata);

        foreach (self::LOCALES as $locale) {
            $translation = $item->translations()->firstOrNew(['locale' => $locale]);

            foreach ($definition[$locale] as $field => $value) {
                if (filled($translation->getAttribute($field)) || ! filled($value)) {
                    continue;
                }

                $translation->setAttribute($field, $value);
            }

            if ($translation->isDirty()) {
                $translation->save();
            }
        }
    }

    /**
     * @return array<string, array{order: int, ar: array<string, mixed>, en: array<string, mixed>}>
     */
    private function sectionDefinitions(): array
    {
        return [
            'hero' => [
                'order' => 0,
                'ar' => ['title' => 'أبرز برامج الجمعية'],
                'en' => ['title' => 'Featured programmes of the association'],
            ],
            'ticker' => [
                'order' => 1,
                'ar' => ['ticker_label' => 'أهم الأخبار'],
                'en' => ['ticker_label' => 'Latest news'],
            ],
            'impact' => [
                'order' => 2,
                'ar' => [
                    'title' => 'بصمتنا في المجتمع',
                    'body' => 'مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.',
                ],
                'en' => [
                    'title' => 'Our imprint on the community',
                    'body' => 'Initial planning targets for 2027, subject to review once available funding and implementation capacity are determined.',
                ],
            ],
            'programs' => [
                'order' => 3,
                'ar' => ['eyebrow' => '01', 'title' => 'برامجنا', 'body' => 'محاور عملنا'],
                'en' => ['eyebrow' => '01', 'title' => 'Our programmes', 'body' => 'Our areas of work'],
            ],
            'news' => [
                'order' => 4,
                'ar' => [
                    'eyebrow' => '02',
                    'title' => 'آخر الأخبار',
                    'tab_labels' => [
                        'news' => 'أخبار وأنشطة',
                        'publications' => 'الإصدارات',
                        'projects' => 'المشاريع',
                    ],
                ],
                'en' => [
                    'eyebrow' => '02',
                    'title' => 'Latest news',
                    'tab_labels' => [
                        'news' => 'News and activities',
                        'publications' => 'Publications',
                        'projects' => 'Projects',
                    ],
                ],
            ],
            'albums' => [
                'order' => 5,
                'ar' => ['eyebrow' => '03', 'title' => 'ألبوم الصور'],
                'en' => ['eyebrow' => '03', 'title' => 'Photo album'],
            ],
            'about' => [
                'order' => 6,
                'ar' => [
                    'eyebrow' => '04',
                    'title' => 'رسالتنا ورؤيتنا',
                    'tab_labels' => [
                        'mission' => 'رسالتنا',
                        'objectives' => 'أهدافنا',
                        'vision' => 'رؤيتنا',
                    ],
                ],
                'en' => [
                    'eyebrow' => '04',
                    'title' => 'Our mission and vision',
                    'tab_labels' => [
                        'mission' => 'Our mission',
                        'objectives' => 'Our objectives',
                        'vision' => 'Our vision',
                    ],
                ],
            ],
            'stories' => [
                'order' => 7,
                'ar' => ['eyebrow' => '05', 'title' => 'قصص نجاح'],
                'en' => ['eyebrow' => '05', 'title' => 'Success stories'],
            ],
            'footer' => [
                'order' => 8,
                'ar' => [
                    'title' => 'عن الجمعية :',
                    'video_caption' => 'الفيلم التعريفي بالجمعية',
                ],
                'en' => [
                    'title' => 'About the association:',
                    'video_caption' => 'Introductory film of the association',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, motif: string, ar: array<string, string>, en: array<string, string>}>
     */
    private function heroSlides(): array
    {
        return [
            'slide-1' => [
                'order' => 0, 'motif' => 'm-sun',
                'ar' => [
                    'label' => 'الشعار الاستراتيجي 2027 – 2029',
                    'title' => 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
                    'body' => 'نحوّل الاحتياجات الميدانية في قطاع غزة إلى برامج ومشاريع قابلة للتنفيذ والقياس والاستدامة، بالشراكة مع المجتمع.',
                ],
                'en' => [
                    'label' => 'Strategic slogan 2027–2029',
                    'title' => 'From humanitarian response to recovery and sustainable development',
                    'body' => 'Turning field needs in Gaza into implementable, measurable and sustainable programmes and projects, in partnership with the community.',
                ],
            ],
            'slide-2' => [
                'order' => 1, 'motif' => 'm-wheat',
                'ar' => [
                    'label' => 'الأمن الغذائي والتغذية',
                    'title' => 'غذاء كريم وعادل ومنتظم للأسر الأكثر احتياجاً',
                    'body' => 'طرود غذائية وكوبونات ومطابخ مجتمعية وحدائق منزلية، وفق معايير استحقاق معلنة وشفافة.',
                ],
                'en' => [
                    'label' => 'Food security and nutrition',
                    'title' => 'Dignified, fair and regular food for the most needy families',
                    'body' => 'Food parcels, vouchers, community kitchens and home gardens, under declared and transparent eligibility criteria.',
                ],
            ],
            'slide-3' => [
                'order' => 2, 'motif' => 'm-bars',
                'ar' => [
                    'label' => 'التمكين الاقتصادي وسبل العيش',
                    'title' => 'من الاعتماد على المساعدات إلى القدرة على الإنتاج',
                    'body' => 'منح صغيرة للمشروعات المنزلية، وتدريب مهني، ودعم للتعاونيات والمبادرات الإنتاجية.',
                ],
                'en' => [
                    'label' => 'Economic empowerment and livelihoods',
                    'title' => 'From dependence on aid to the ability to produce',
                    'body' => 'Small grants for home-based projects, vocational training, and support for cooperatives and productive initiatives.',
                ],
            ],
            'slide-4' => [
                'order' => 3, 'motif' => 'm-home',
                'ar' => [
                    'label' => 'الحماية والدعم النفسي والاجتماعي',
                    'title' => 'رعاية متكاملة للفئات الأكثر هشاشة',
                    'body' => 'الأيتام والأشخاص ذوو الإعاقة والنساء المعيلات وكبار السن، مع دعم نفسي واجتماعي للأطفال والأسر.',
                ],
                'en' => [
                    'label' => 'Protection and psychosocial support',
                    'title' => 'Integrated care for the most vulnerable groups',
                    'body' => 'Orphans, people with disabilities, female-headed households and older people, with psychosocial support for children and families.',
                ],
            ],
            'slide-5' => [
                'order' => 4, 'motif' => 'm-drop',
                'ar' => [
                    'label' => 'الصحة والمياه والإصحاح البيئي',
                    'title' => 'بيئة أكثر أماناً في مراكز الإيواء والمناطق المتضررة',
                    'body' => 'حملات نظافة وتوعية صحية، ودعم مياه الشرب الآمنة، وتحسين مرافق الصرف الصحي.',
                ],
                'en' => [
                    'label' => 'Health, water and environmental sanitation',
                    'title' => 'A safer environment in shelters and affected areas',
                    'body' => 'Hygiene campaigns and health awareness, support for safe drinking water, and improved sanitation facilities.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function tickerHeadlines(): array
    {
        return [
            'ticker-1' => [
                'order' => 0,
                'ar' => [
                    'label' => 'أهم الأخبار',
                    'title' => 'الخطة الاستراتيجية والتشغيلية 2027–2029: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
                    'url' => '#news',
                ],
                'en' => [
                    'label' => 'Latest news',
                    'title' => 'Strategic and operational plan 2027–2029: from humanitarian response to recovery and sustainable development',
                    'url' => '#news',
                ],
            ],
            'ticker-2' => [
                'order' => 1,
                'ar' => [
                    'label' => 'أهم الأخبار',
                    'title' => 'المرحلة الأولى 2027: التأسيس المؤسسي وبناء الجاهزية الإدارية والفنية',
                    'url' => '#news',
                ],
                'en' => [
                    'label' => 'Latest news',
                    'title' => 'Phase one 2027: institutional foundation and administrative and technical readiness',
                    'url' => '#news',
                ],
            ],
            'ticker-3' => [
                'order' => 2,
                'ar' => [
                    'label' => 'أهم الأخبار',
                    'title' => 'إطلاق البرامج الميدانية: المساعدات الغذائية والدعم النفسي والاجتماعي',
                    'url' => '#news',
                ],
                'en' => [
                    'label' => 'Latest news',
                    'title' => 'Launch of field programmes: food assistance and psychosocial support',
                    'url' => '#news',
                ],
            ],
            'ticker-4' => [
                'order' => 3,
                'ar' => [
                    'label' => 'أهم الأخبار',
                    'title' => 'مستهدفنا لعام 2027: 3,000 أسرة مستفيدة من التدخلات الإنسانية',
                    'url' => '#targets',
                ],
                'en' => [
                    'label' => 'Latest news',
                    'title' => 'Our 2027 target: 3,000 families benefiting from humanitarian interventions',
                    'url' => '#targets',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, value: float, ar: array<string, string>, en: array<string, string>}>
     */
    private function impactItems(): array
    {
        return [
            'stat-axes' => [
                'order' => 0, 'value' => 11,
                'ar' => [
                    'title' => 'مجالات عملنا',
                    'body' => 'حسب أهدافنا الاستراتيجية',
                    'display_value' => '11',
                    'unit' => 'محوراً',
                    'url' => '#programs',
                ],
                'en' => [
                    'title' => 'Our areas of work',
                    'body' => 'According to our strategic goals',
                    'display_value' => '11',
                    'unit' => 'axes',
                    'url' => '#programs',
                ],
            ],
            'stat-families' => [
                'order' => 1, 'value' => 3000,
                'ar' => [
                    'title' => 'مستهدفنا لعام 2027',
                    'body' => 'أسرة مستفيدة من التدخلات الإنسانية',
                    'display_value' => '3,000',
                    'unit' => 'أسرة',
                    'url' => '#targets',
                ],
                'en' => [
                    'title' => 'Our target for 2027',
                    'body' => 'families benefiting from humanitarian interventions',
                    'display_value' => '3,000',
                    'unit' => 'families',
                    'url' => '#targets',
                ],
            ],
            'target-food' => [
                'order' => 2, 'value' => 1500,
                'ar' => ['title' => 'أسرة من برامج الأمن الغذائي', 'body' => 'أسرة من برامج الأمن الغذائي', 'display_value' => '1,500'],
                'en' => ['title' => 'families from food security programmes', 'body' => 'families from food security programmes', 'display_value' => '1,500'],
            ],
            'target-projects' => [
                'order' => 3, 'value' => 50,
                'ar' => ['title' => 'مشروعاً صغيراً مدعوماً', 'body' => 'مشروعاً صغيراً مدعوماً', 'display_value' => '50'],
                'en' => ['title' => 'supported small projects', 'body' => 'supported small projects', 'display_value' => '50'],
            ],
            'target-orphans' => [
                'order' => 4, 'value' => 100,
                'ar' => ['title' => 'يتيم من برامج الرعاية', 'body' => 'يتيم من برامج الرعاية', 'display_value' => '100'],
                'en' => ['title' => 'orphans in care programmes', 'body' => 'orphans in care programmes', 'display_value' => '100'],
            ],
            'target-disabilities' => [
                'order' => 5, 'value' => 100,
                'ar' => ['title' => 'شخص من ذوي الإعاقة', 'body' => 'شخص من ذوي الإعاقة', 'display_value' => '100'],
                'en' => ['title' => 'people with disabilities', 'body' => 'people with disabilities', 'display_value' => '100'],
            ],
            'target-psychosocial' => [
                'order' => 6, 'value' => 500,
                'ar' => ['title' => 'مستفيد من الدعم النفسي والاجتماعي', 'body' => 'مستفيد من الدعم النفسي والاجتماعي', 'display_value' => '500'],
                'en' => ['title' => 'beneficiaries of psychosocial support', 'body' => 'beneficiaries of psychosocial support', 'display_value' => '500'],
            ],
            'target-youth' => [
                'order' => 7, 'value' => 200,
                'ar' => ['title' => 'شاب وشابة في التدريب', 'body' => 'شاب وشابة في التدريب', 'display_value' => '200'],
                'en' => ['title' => 'young men and women in training', 'body' => 'young men and women in training', 'display_value' => '200'],
            ],
            'target-volunteers' => [
                'order' => 8, 'value' => 100,
                'ar' => ['title' => 'متطوع مسجل', 'body' => 'متطوع مسجل', 'display_value' => '100'],
                'en' => ['title' => 'registered volunteers', 'body' => 'registered volunteers', 'display_value' => '100'],
            ],
            'target-committees' => [
                'order' => 9, 'value' => 10,
                'ar' => ['title' => 'لجان مجتمعية نشطة', 'body' => 'لجان مجتمعية نشطة', 'display_value' => '10'],
                'en' => ['title' => 'active community committees', 'body' => 'active community committees', 'display_value' => '10'],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function programAxes(): array
    {
        $arabic = [
            ['الاستجابة الإنسانية والإغاثية وإدارة الكوارث', 'منظومة استجابة سريعة ومنظمة للأزمات، وتلبية الاحتياجات الأساسية للأسر المتضررة.'],
            ['الأمن الغذائي والتغذية', 'وصول عادل ومنتظم للمساعدات الغذائية، ومطابخ مجتمعية وحدائق منزلية.'],
            ['التنمية الاقتصادية وسبل العيش', 'الانتقال بالأسر من الاعتماد على المساعدات إلى الإنتاج والدخل المستدام.'],
            ['الحماية الاجتماعية والرعاية النفسية', 'رعاية الأيتام والأشخاص ذوي الإعاقة والنساء المعيلات وكبار السن.'],
            ['الصحة العامة والمياه والإصحاح البيئي', 'مياه شرب آمنة، وحملات نظافة وتوعية، وتحسين المرافق في مراكز الإيواء.'],
            ['التعليم وبناء القدرات وتنمية الطفولة والشباب', 'الحد من الآثار التعليمية للأزمات وتنمية مهارات الأطفال والشباب.'],
            ['الإسكان والتعافي المبكر وإعادة الإعمار المجتمعي', 'ترميم المساكن وإعادة تأهيل المرافق المجتمعية الصغيرة.'],
            ['المشاركة المجتمعية والتطوع والمسؤولية الاجتماعية', 'لجان عمل تشاركي وفرق تطوعية وشراكة مع القطاع الخاص.'],
            ['الشراكات المحلية والدولية واستقطاب التمويل', 'علاقات مؤسسية مع الجهات المحلية والدولية ووحدة لتطوير المشاريع.'],
            ['الإعلام والاتصال والمناصرة المجتمعية', 'توثيق الاحتياجات ونتائج التدخلات والتواصل مع الجمهور والشركاء.'],
            ['البحث والدراسات وتقييم الاحتياجات', 'مسوح وخرائط احتياجات وتقييم أثر المشاريع لبناء قرارات موثوقة.'],
        ];
        $english = [
            ['Humanitarian response, relief and disaster management', 'A rapid, organized response system for crises, meeting the basic needs of affected families.'],
            ['Food security and nutrition', 'Fair and regular access to food assistance, community kitchens and home gardens.'],
            ['Economic development and livelihoods', 'Moving families from dependence on aid to production and sustainable income.'],
            ['Social protection and psychosocial care', 'Care for orphans, people with disabilities, female-headed households and older people.'],
            ['Public health, water and environmental sanitation', 'Safe drinking water, hygiene and awareness campaigns, and improved facilities in shelters.'],
            ['Education, capacity building, child and youth development', 'Reducing the educational impact of crises and developing children’s and young people’s skills.'],
            ['Housing, early recovery and community reconstruction', 'Repairing homes and rehabilitating small community facilities.'],
            ['Community participation, volunteering and social responsibility', 'Participatory committees, volunteer teams and partnerships with the private sector.'],
            ['Local and international partnerships and resource mobilization', 'Institutional relations with local and international bodies and a project development unit.'],
            ['Information, communication and community advocacy', 'Documenting needs and intervention results and communicating with the public and partners.'],
            ['Research, studies and needs assessment', 'Surveys, needs maps and project impact evaluations to support reliable decisions.'],
        ];

        $items = [];
        foreach ($arabic as $index => [$title, $body]) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $items['axis-'.$number] = [
                'order' => $index,
                'ar' => ['label' => $number, 'title' => $title, 'body' => $body],
                'en' => ['label' => $number, 'title' => $english[$index][0], 'body' => $english[$index][1]],
            ];
        }

        return $items;
    }

    /**
     * Source links are placeholders written as "#"; they are stored as null.
     *
     * @return array<string, array{order: int, tab: string, featured: bool, motif: string, ar: array<string, string>, en: array<string, string>}>
     */
    private function newsItems(): array
    {
        return [
            'news-feature' => [
                'order' => 0, 'tab' => 'news', 'featured' => true, 'motif' => 'm-sun',
                'ar' => [
                    'title' => 'الخطة الاستراتيجية والتشغيلية 2027–2029: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة',
                    'date_label' => 'الإطار الزمني 2027 – 2029',
                    'caption' => 'تؤسس الجمعية للانتقال من التدخلات الإغاثية الموسمية إلى نموذج مؤسسي متكامل يجمع بين الاستجابة الإنسانية الفورية والحماية الاجتماعية والتنمية الاقتصادية والتعافي المبكر…',
                    'cta_label' => 'أكمل القراءة',
                ],
                'en' => [
                    'title' => 'Strategic and operational plan 2027–2029: from humanitarian response to recovery and sustainable development',
                    'date_label' => 'Time frame 2027 – 2029',
                    'caption' => 'The association is laying the foundations for moving from seasonal relief interventions to an integrated institutional model that combines immediate humanitarian response, social protection, economic development and early recovery…',
                    'cta_label' => 'Continue reading',
                ],
            ],
            'news-phase-one' => [
                'order' => 1, 'tab' => 'news', 'featured' => false, 'motif' => 'm-people',
                'ar' => ['title' => 'المرحلة الأولى: التأسيس المؤسسي وبناء الجاهزية الإدارية والفنية', 'date_label' => 'يناير – مارس 2027'],
                'en' => ['title' => 'Phase one: institutional foundation and administrative and technical readiness', 'date_label' => 'January – March 2027'],
            ],
            'news-field-programmes' => [
                'order' => 2, 'tab' => 'news', 'featured' => false, 'motif' => 'm-wheat',
                'ar' => ['title' => 'إطلاق البرامج الميدانية: المساعدات الغذائية والدعم النفسي والاجتماعي', 'date_label' => 'أبريل – يونيو 2027'],
                'en' => ['title' => 'Launch of field programmes: food assistance and psychosocial support', 'date_label' => 'April – June 2027'],
            ],
            'news-partnerships' => [
                'order' => 3, 'tab' => 'news', 'featured' => false, 'motif' => 'm-home',
                'ar' => ['title' => 'التوسع في الشراكات وبرامج رعاية الأيتام والأشخاص ذوي الإعاقة', 'date_label' => 'يوليو – سبتمبر 2027'],
                'en' => ['title' => 'Expansion of partnerships and programmes for the care of orphans and people with disabilities', 'date_label' => 'July – September 2027'],
            ],
            'pub-feature' => [
                'order' => 4, 'tab' => 'publications', 'featured' => true, 'motif' => 'm-doc',
                'ar' => [
                    'title' => 'الخطة الاستراتيجية والتشغيلية الشاملة للجمعية',
                    'date_label' => '2027 – 2029',
                    'caption' => 'الإطار المؤسسي والتوجيهي لعمل الجمعية: الرؤية والرسالة والقيم، والمحاور الاستراتيجية الأحد عشر، والهيكل التنظيمي، والخطة التشغيلية للسنة الأولى، ومؤشرات الأداء والموازنة…',
                    'cta_label' => 'تحميل الإصدار',
                ],
                'en' => [
                    'title' => 'The association’s comprehensive strategic and operational plan',
                    'date_label' => '2027 – 2029',
                    'caption' => 'The institutional and guidance framework for the association’s work: vision, mission and values, the eleven strategic axes, the organizational structure, the first-year operational plan, performance indicators and budget…',
                    'cta_label' => 'Download the publication',
                ],
            ],
            'pub-profile' => [
                'order' => 5, 'tab' => 'publications', 'featured' => false, 'motif' => 'm-book',
                'ar' => ['title' => 'الملف التعريفي للجمعية (عربي / إنجليزي)', 'date_label' => 'مرحلة التأسيس'],
                'en' => ['title' => 'Association profile (Arabic / English)', 'date_label' => 'Foundation phase'],
            ],
            'pub-logo-guide' => [
                'order' => 6, 'tab' => 'publications', 'featured' => false, 'motif' => 'm-doc',
                'ar' => ['title' => 'دليل استخدام الشعار والهوية البصرية', 'date_label' => 'مرحلة التأسيس'],
                'en' => ['title' => 'Logo and visual identity usage guide', 'date_label' => 'Foundation phase'],
            ],
            'pub-performance' => [
                'order' => 7, 'tab' => 'publications', 'featured' => false, 'motif' => 'm-bars',
                'ar' => ['title' => 'تقرير الأداء نصف السنوي', 'date_label' => 'يوليو – سبتمبر 2027'],
                'en' => ['title' => 'Semi-annual performance report', 'date_label' => 'July – September 2027'],
            ],
            'project-feature' => [
                'order' => 8, 'tab' => 'projects', 'featured' => true, 'motif' => 'm-wheat',
                'ar' => [
                    'title' => 'مشروع الأمن الغذائي والمساعدات النقدية',
                    'date_label' => 'أولوية تنفيذية عاجلة',
                    'caption' => 'توزيع الطرود الغذائية والكوبونات وتوفير المساعدات النقدية والعينية للأسر الأكثر احتياجاً، وفق معايير استحقاق معلنة وشفافة وبما يضمن عدم الازدواجية…',
                    'cta_label' => 'المزيد عن المشروع',
                ],
                'en' => [
                    'title' => 'Food security and cash assistance project',
                    'date_label' => 'Urgent implementation priority',
                    'caption' => 'Distributing food parcels and vouchers and providing cash and in-kind assistance to the most needy families, under declared and transparent eligibility criteria and without duplication…',
                    'cta_label' => 'More about the project',
                ],
            ],
            'project-orphans' => [
                'order' => 9, 'tab' => 'projects', 'featured' => false, 'motif' => 'm-people',
                'ar' => ['title' => 'برنامج متكامل لرعاية الأيتام والأسر الأكثر هشاشة', 'date_label' => 'أولوية تنفيذية عاجلة'],
                'en' => ['title' => 'An integrated programme for the care of orphans and the most vulnerable families', 'date_label' => 'Urgent implementation priority'],
            ],
            'project-economic' => [
                'order' => 10, 'tab' => 'projects', 'featured' => false, 'motif' => 'm-bars',
                'ar' => ['title' => 'التمكين الاقتصادي والمشروعات الصغيرة', 'date_label' => 'أولوية تنفيذية عاجلة'],
                'en' => ['title' => 'Economic empowerment and small projects', 'date_label' => 'Urgent implementation priority'],
            ],
            'project-psychosocial' => [
                'order' => 11, 'tab' => 'projects', 'featured' => false, 'motif' => 'm-home',
                'ar' => ['title' => 'الدعم النفسي والاجتماعي للأطفال والأسر', 'date_label' => 'أولوية تنفيذية عاجلة'],
                'en' => ['title' => 'Psychosocial support for children and families', 'date_label' => 'Urgent implementation priority'],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, motif: string, ar: array<string, string>, en: array<string, string>}>
     */
    private function albumItems(): array
    {
        $captions = [
            ['الاستجابة الطارئة وتوزيع المساعدات على الأسر المتضررة', 'Emergency response and relief distribution to affected families', 'm-people'],
            ['توزيع الطرود الغذائية', 'Distribution of food parcels', 'm-wheat'],
            ['الدعم النفسي والاجتماعي للأطفال', 'Psychosocial support for children', 'm-home'],
            ['نظافة مراكز الإيواء', 'Hygiene in shelters', 'm-drop'],
            ['دعم المشروعات الصغيرة', 'Support for small projects', 'm-bars'],
        ];

        $items = [];
        foreach ($captions as $index => [$arabic, $english, $motif]) {
            $items['album-'.($index + 1)] = [
                'order' => $index,
                'motif' => $motif,
                'ar' => ['label' => $arabic, 'title' => $arabic, 'caption' => $arabic],
                'en' => ['label' => $english, 'title' => $english, 'caption' => $english],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{order: int, tab: string|null, ar: array<string, string>, en: array<string, string>}>
     */
    private function aboutItems(): array
    {
        $items = [
            'mission' => [
                'order' => 0,
                'tab' => 'mission',
                'ar' => [
                    'title' => 'رسالتنا',
                    'body' => 'تنسيق الجهود الإنسانية وتعزيز الشراكات المحلية والدولية، وتقديم استجابات إغاثية وتنموية متكاملة، وتمكين الفئات الأكثر هشاشة، وتحسين الظروف المعيشية للأسر المتضررة والفقيرة، من خلال برامج مهنية ومستدامة تقوم على المشاركة المجتمعية والشفافية والمساءلة.',
                ],
                'en' => [
                    'title' => 'Our mission',
                    'body' => 'Coordinating humanitarian efforts, strengthening local and international partnerships, providing integrated relief and development responses, empowering the most vulnerable groups, and improving living conditions for affected and poor families through professional and sustainable programmes grounded in community participation, transparency and accountability.',
                ],
            ],
        ];

        $arabicObjectives = [
            'تطوير منظومة متكاملة للاستجابة الإنسانية وإدارة الأزمات والكوارث.',
            'تعزيز الأمن الغذائي وتحسين الظروف المعيشية للأسر الفقيرة والمتضررة.',
            'تمكين الأسر اقتصادياً وتعزيز فرص العمل والإنتاج ومكافحة الفقر.',
            'توفير خدمات الحماية الاجتماعية والرعاية النفسية والاجتماعية للفئات الأكثر هشاشة.',
            'تحسين الصحة العامة والمياه والإصحاح البيئي.',
            'دعم التعليم وتنمية قدرات الأطفال والشباب.',
            'المساهمة في التعافي المبكر وتحسين ظروف السكن وإعادة تأهيل المرافق المجتمعية.',
            'تعزيز المشاركة المجتمعية والتطوع والعمل التشاركي.',
            'تطوير الشراكات المحلية والدولية واستقطاب التمويل.',
            'بناء منظومة إعلام واتصال ومناصرة مجتمعية فاعلة.',
            'تعزيز البحث والدراسات وتقييم الاحتياجات وقياس أثر التدخلات.',
        ];
        $englishObjectives = [
            'Develop an integrated system for humanitarian response and disaster and crisis management.',
            'Strengthen food security and improve living conditions for poor and affected families.',
            'Empower families economically, promote work and production, and combat poverty.',
            'Provide social protection and psychosocial care services for the most vulnerable groups.',
            'Improve public health, water and environmental sanitation.',
            'Support education and develop the capacities of children and young people.',
            'Contribute to early recovery, improve housing conditions and rehabilitate community facilities.',
            'Promote community participation, volunteering and participatory action.',
            'Develop local and international partnerships and mobilize funding.',
            'Build an effective information, communication and community advocacy system.',
            'Strengthen research, studies, needs assessment and measurement of intervention impact.',
        ];

        foreach ($arabicObjectives as $index => $text) {
            $items['objective-'.($index + 1)] = [
                'order' => 1 + $index,
                'tab' => 'objectives',
                'ar' => ['label' => $text, 'title' => $text],
                'en' => ['label' => $englishObjectives[$index], 'title' => $englishObjectives[$index]],
            ];
        }

        $items['vision'] = [
            'order' => 12,
            'tab' => 'vision',
            'ar' => [
                'title' => 'رؤيتنا',
                'body' => 'الريادة في العمل الإنساني والتنموي التشاركي، والمساهمة في بناء مجتمع فلسطيني أكثر قدرة على الصمود والتعافي، يتمتع فيه الأفراد والأسر بالكرامة والعدالة وتكافؤ الفرص.',
            ],
            'en' => [
                'title' => 'Our vision',
                'body' => 'Leadership in participatory humanitarian and development work, contributing to building a more resilient and recovery-capable Palestinian society in which individuals and families enjoy dignity, justice and equal opportunity.',
            ],
        ];

        $arabicValues = [
            'الإنسانية وصون الكرامة', 'العدالة وعدم التمييز', 'النزاهة والشفافية والمساءلة',
            'المشاركة المجتمعية والتطوع', 'المهنية والكفاءة والاستدامة', 'الشراكة والتكامل',
            'الاستجابة السريعة والمرونة', 'حماية الفئات الأكثر هشاشة',
            'احترام الخصوصية وحماية البيانات', 'التعلم والتحسين المستمر',
        ];
        $englishValues = [
            'Humanity and preserving dignity', 'Justice and non-discrimination', 'Integrity, transparency and accountability',
            'Community participation and volunteering', 'Professionalism, efficiency and sustainability', 'Partnership and integration',
            'Rapid response and resilience', 'Protecting the most vulnerable groups',
            'Respect for privacy and data protection', 'Learning and continuous improvement',
        ];

        foreach ($arabicValues as $index => $text) {
            $items['value-'.($index + 1)] = [
                'order' => 13 + $index,
                'tab' => 'vision',
                'ar' => ['label' => $text, 'title' => $text],
                'en' => ['label' => $englishValues[$index], 'title' => $englishValues[$index]],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{order: int, motif: string, ar: array<string, string>, en: array<string, string>}>
     */
    private function storyItems(): array
    {
        return [
            'story-1' => [
                'order' => 0, 'motif' => 'm-bars',
                'ar' => [
                    'title' => 'مشروع منزلي يعيد الدخل لأسرة',
                    'body' => 'برنامج التمكين الاقتصادي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.',
                ],
                'en' => [
                    'title' => 'A home-based project restoring a family’s income',
                    'body' => 'Economic empowerment programme. The story will be published only with the beneficiary’s consent and in a way that protects privacy.',
                ],
            ],
            'story-2' => [
                'order' => 1, 'motif' => 'm-wheat',
                'ar' => [
                    'title' => 'مطبخ مجتمعي يخدم الحي',
                    'body' => 'برنامج الأمن الغذائي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.',
                ],
                'en' => [
                    'title' => 'A community kitchen serving the neighbourhood',
                    'body' => 'Food security programme. The story will be published only with the beneficiary’s consent and in a way that protects privacy.',
                ],
            ],
            'story-3' => [
                'order' => 2, 'motif' => 'm-home',
                'ar' => [
                    'title' => 'دعم نفسي واجتماعي للأطفال',
                    'body' => 'برنامج الحماية والرعاية. تُنشر القصة بعد موافقة ولي الأمر وبما يحمي خصوصية الطفل.',
                ],
                'en' => [
                    'title' => 'Psychosocial support for children',
                    'body' => 'Protection and care programme. The story will be published only with the guardian’s consent and in a way that protects the child’s privacy.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function footerItems(): array
    {
        return [
            'footer-about' => [
                'order' => 0,
                'ar' => [
                    'title' => 'عن الجمعية :',
                    'body' => 'جمعية العمل التشاركي في الأزمات والكوارث الخيرية جمعية أهلية في قطاع غزة، تعمل على الانتقال من التدخلات الإغاثية الموسمية إلى نموذج مؤسسي متكامل يجمع بين الاستجابة الإنسانية الفورية والحماية الاجتماعية والتنمية الاقتصادية والتعافي المبكر، وتجعل المجتمع شريكاً أساسياً في تحديد الاحتياجات وتنفيذ التدخلات وتقييم أثرها…',
                    'cta_label' => 'اقرأ المزيد',
                    'url' => '#about',
                ],
                'en' => [
                    'title' => 'About the association:',
                    'body' => 'The Charitable Society for Participatory Action in Crises and Disasters is a civil society organization in Gaza working to move from seasonal relief interventions to an integrated institutional model that combines immediate humanitarian response, social protection, economic development and early recovery, and makes the community a core partner in identifying needs, implementing interventions and evaluating their impact…',
                    'cta_label' => 'Read more',
                    'url' => '#about',
                ],
            ],
            'footer-contact' => [
                'order' => 1,
                'ar' => [
                    'title' => 'اتصل بنا / الشكاوى :',
                    'body' => 'نوفر قنوات آمنة وسرية لتقديم الشكاوى والملاحظات، ونلتزم بالرد عليها وفق سياسة الشكاوى والتظلمات المعتمدة.',
                    'cta_label' => 'اتصل بنا / الشكاوى',
                    'url' => '#contact',
                ],
                'en' => [
                    'title' => 'Contact us / complaints:',
                    'body' => 'We provide safe and confidential channels for submitting complaints and feedback, and we commit to responding to them under the approved complaints and grievances policy.',
                    'cta_label' => 'Contact us / complaints',
                    'url' => '#contact',
                ],
            ],
            'footer-video' => [
                'order' => 2,
                'ar' => [
                    'title' => 'المرئيات :',
                    'label' => 'الفيلم التعريفي بالجمعية',
                    'caption' => 'الفيلم التعريفي بالجمعية',
                    'cta_label' => 'الفيلم التعريفي بالجمعية',
                ],
                'en' => [
                    'title' => 'Audiovisuals:',
                    'label' => 'Introductory film of the association',
                    'caption' => 'Introductory film of the association',
                    'cta_label' => 'Introductory film of the association',
                ],
            ],
            'social-rss' => [
                'order' => 3,
                'ar' => ['label' => 'خلاصة RSS'],
                'en' => ['label' => 'RSS feed'],
            ],
            'social-youtube' => [
                'order' => 4,
                'ar' => ['label' => 'يوتيوب'],
                'en' => ['label' => 'YouTube'],
            ],
            'social-x' => [
                'order' => 5,
                'ar' => ['label' => 'منصة X'],
                'en' => ['label' => 'X'],
            ],
            'social-facebook' => [
                'order' => 6,
                'ar' => ['label' => 'فيسبوك'],
                'en' => ['label' => 'Facebook'],
            ],
        ];
    }
}
