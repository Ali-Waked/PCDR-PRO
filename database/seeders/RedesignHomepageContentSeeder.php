<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

/**
 * Adds only source-derived homepage items to the canonical ten sections.
 *
 * Every value below is transcribed from the supplied homepage or translated
 * from verified Arabic source copy. Placeholder items carry no media reference
 * and remain explicitly provisional. Administrator edits are never overwritten.
 */
final class RedesignHomepageContentSeeder extends Seeder
{
    private const LOCALES = ['ar', 'en'];

    public function run(): void
    {
        $this->ensureCanonicalSections();

        foreach ($this->heroSlides() as $itemKey => $definition) {
            $this->seedItem('hero', $itemKey, $definition);
        }

        foreach ($this->tickerHeadlines() as $itemKey => $definition) {
            $this->seedItem('hero', $itemKey, $definition);
        }

        foreach ($this->extraTargets() as $itemKey => $definition) {
            $this->seedItem('targets', $itemKey, $definition);
        }

        foreach ($this->missionObjectives() as $itemKey => $definition) {
            $this->seedItem('intro', $itemKey, $definition);
        }

        foreach ($this->institutionalValues() as $itemKey => $definition) {
            $this->seedItem('intro', $itemKey, $definition);
        }

        foreach ($this->programAxes() as $itemKey => $definition) {
            $this->seedItem('programs', $itemKey, $definition);
        }

        foreach ($this->albumPlaceholders() as $itemKey => $definition) {
            $this->seedItem('community', $itemKey, $definition);
        }

        foreach ($this->storyPlaceholders() as $itemKey => $definition) {
            $this->seedItem('community', $itemKey, $definition);
        }
    }

    private function ensureCanonicalSections(): void
    {
        foreach (['hero', 'targets', 'intro', 'programs', 'community'] as $order => $key) {
            HomepageSection::query()->firstOrCreate(
                ['key' => $key],
                [
                    'type' => $key,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_enabled' => true,
                    'display_order' => $order,
                ],
            );
        }
    }

    /**
     * @param  array{order: int, value?: float, ar: array<string, string|null>, en: array<string, string|null>}  $definition
     */
    private function seedItem(string $sectionKey, string $itemKey, array $definition): void
    {
        $section = HomepageSection::query()->where('key', $sectionKey)->firstOrFail();

        $item = $section->items()->firstOrCreate(
            ['item_key' => $itemKey],
            [
                'item_type' => 'inline',
                'item_id' => null,
                'media_id' => null,
                'is_published' => true,
                'numeric_value' => $definition['value'] ?? null,
                'sort_order' => $definition['order'],
            ],
        );

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
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function heroSlides(): array
    {
        return [
            'slide-1' => [
                'order' => 10,
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
                'order' => 11,
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
                'order' => 12,
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
                'order' => 13,
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
                'order' => 14,
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
                'order' => 20,
                'ar' => ['label' => 'أهم الأخبار', 'title' => 'الخطة الاستراتيجية والتشغيلية 2027–2029: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة'],
                'en' => ['label' => 'Latest news', 'title' => 'Strategic and operational plan 2027–2029: from humanitarian response to recovery and sustainable development'],
            ],
            'ticker-2' => [
                'order' => 21,
                'ar' => ['label' => 'أهم الأخبار', 'title' => 'المرحلة الأولى 2027: التأسيس المؤسسي وبناء الجاهزية الإدارية والفنية'],
                'en' => ['label' => 'Latest news', 'title' => 'Phase one 2027: institutional foundation and administrative and technical readiness'],
            ],
            'ticker-3' => [
                'order' => 22,
                'ar' => ['label' => 'أهم الأخبار', 'title' => 'إطلاق البرامج الميدانية: المساعدات الغذائية والدعم النفسي والاجتماعي'],
                'en' => ['label' => 'Latest news', 'title' => 'Launch of field programmes: food assistance and psychosocial support'],
            ],
            'ticker-4' => [
                'order' => 23,
                'ar' => ['label' => 'أهم الأخبار', 'title' => 'مستهدفنا لعام 2027: 3,000 أسرة مستفيدة من التدخلات الإنسانية'],
                'en' => ['label' => 'Latest news', 'title' => 'Our 2027 target: 3,000 families benefiting from humanitarian interventions'],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, value: float, ar: array<string, string>, en: array<string, string>}>
     */
    private function extraTargets(): array
    {
        return [
            'target-orphans' => [
                'order' => 10, 'value' => 100,
                'ar' => ['title' => 'يتيم من برامج الرعاية', 'body' => 'يتيم من برامج الرعاية', 'display_value' => '100'],
                'en' => ['title' => 'Orphans in care programmes', 'body' => 'Orphans in care programmes', 'display_value' => '100'],
            ],
            'target-disabilities' => [
                'order' => 11, 'value' => 100,
                'ar' => ['title' => 'شخص من ذوي الإعاقة', 'body' => 'شخص من ذوي الإعاقة', 'display_value' => '100'],
                'en' => ['title' => 'People with disabilities', 'body' => 'People with disabilities', 'display_value' => '100'],
            ],
            'target-psychosocial' => [
                'order' => 12, 'value' => 500,
                'ar' => ['title' => 'مستفيد من الدعم النفسي والاجتماعي', 'body' => 'مستفيد من الدعم النفسي والاجتماعي', 'display_value' => '500'],
                'en' => ['title' => 'Beneficiaries of psychosocial support', 'body' => 'Beneficiaries of psychosocial support', 'display_value' => '500'],
            ],
            'target-committees' => [
                'order' => 13, 'value' => 10,
                'ar' => ['title' => 'لجان مجتمعية نشطة', 'body' => 'لجان مجتمعية نشطة', 'display_value' => '10'],
                'en' => ['title' => 'Active community committees', 'body' => 'Active community committees', 'display_value' => '10'],
            ],
        ];
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function missionObjectives(): array
    {
        $arabic = [
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
        $english = [
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

        return $this->pairedList('objective', $arabic, $english, 10);
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function institutionalValues(): array
    {
        $arabic = [
            'الإنسانية وصون الكرامة', 'العدالة وعدم التمييز', 'النزاهة والشفافية والمساءلة',
            'المشاركة المجتمعية والتطوع', 'المهنية والكفاءة والاستدامة', 'الشراكة والتكامل',
            'الاستجابة السريعة والمرونة', 'حماية الفئات الأكثر هشاشة',
            'احترام الخصوصية وحماية البيانات', 'التعلم والتحسين المستمر',
        ];
        $english = [
            'Humanity and preserving dignity', 'Justice and non-discrimination', 'Integrity, transparency and accountability',
            'Community participation and volunteering', 'Professionalism, efficiency and sustainability', 'Partnership and integration',
            'Rapid response and resilience', 'Protecting the most vulnerable groups',
            'Respect for privacy and data protection', 'Learning and continuous improvement',
        ];

        return $this->pairedList('value', $arabic, $english, 30);
    }

    /**
     * @param  list<string>  $arabic
     * @param  list<string>  $english
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function pairedList(string $prefix, array $arabic, array $english, int $startOrder): array
    {
        $items = [];

        foreach ($arabic as $index => $text) {
            $items[$prefix.'-'.($index + 1)] = [
                'order' => $startOrder + $index,
                'ar' => ['title' => $text, 'label' => $text],
                'en' => ['title' => $english[$index], 'label' => $english[$index]],
            ];
        }

        return $items;
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
            $items['axis-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = [
                'order' => 50 + $index,
                'ar' => ['title' => $title, 'label' => $title, 'body' => $body],
                'en' => ['title' => $english[$index][0], 'label' => $english[$index][0], 'body' => $english[$index][1]],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function albumPlaceholders(): array
    {
        $captions = [
            ['الاستجابة الطارئة وتوزيع المساعدات على الأسر المتضررة', 'Emergency response and relief distribution to affected families'],
            ['توزيع الطرود الغذائية', 'Distribution of food parcels'],
            ['الدعم النفسي والاجتماعي للأطفال', 'Psychosocial support for children'],
            ['نظافة مراكز الإيواء', 'Hygiene in shelters'],
            ['دعم المشروعات الصغيرة', 'Support for small projects'],
        ];

        $items = [];
        foreach ($captions as $index => [$arabic, $english]) {
            $items['album-'.($index + 1)] = [
                'order' => 70 + $index,
                'ar' => ['title' => $arabic, 'label' => $arabic, 'body' => 'عنصر توضيحي مؤقت؛ لا توجد صورة موثقة بعد.'],
                'en' => ['title' => $english, 'label' => $english, 'body' => 'Placeholder only; no verified photograph available yet.'],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{order: int, ar: array<string, string>, en: array<string, string>}>
     */
    private function storyPlaceholders(): array
    {
        return [
            'story-1' => [
                'order' => 80,
                'ar' => ['title' => 'مشروع منزلي يعيد الدخل لأسرة', 'label' => 'مشروع منزلي يعيد الدخل لأسرة', 'body' => 'برنامج التمكين الاقتصادي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.'],
                'en' => ['title' => 'A home-based project restoring a family’s income', 'label' => 'A home-based project restoring a family’s income', 'body' => 'Economic empowerment programme. The story will be published only with the beneficiary’s consent and in a way that protects privacy.'],
            ],
            'story-2' => [
                'order' => 81,
                'ar' => ['title' => 'مطبخ مجتمعي يخدم الحي', 'label' => 'مطبخ مجتمعي يخدم الحي', 'body' => 'برنامج الأمن الغذائي. تُنشر القصة بعد موافقة المستفيد وبما يحمي خصوصيته.'],
                'en' => ['title' => 'A community kitchen serving the neighbourhood', 'label' => 'A community kitchen serving the neighbourhood', 'body' => 'Food security programme. The story will be published only with the beneficiary’s consent and in a way that protects privacy.'],
            ],
            'story-3' => [
                'order' => 82,
                'ar' => ['title' => 'دعم نفسي واجتماعي للأطفال', 'label' => 'دعم نفسي واجتماعي للأطفال', 'body' => 'برنامج الحماية والرعاية. تُنشر القصة بعد موافقة ولي الأمر وبما يحمي خصوصية الطفل.'],
                'en' => ['title' => 'Psychosocial support for children', 'label' => 'Psychosocial support for children', 'body' => 'Protection and care programme. The story will be published only with the guardian’s consent and in a way that protects the child’s privacy.'],
            ],
        ];
    }
}
