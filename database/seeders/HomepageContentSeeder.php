<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

/**
 * Initializes the homepage sections actually rendered by Public/Home.vue.
 *
 * All values are sourced from docs/strategy/STRATEGIC_OPERATIONAL_PLAN_2027_2029.md,
 * the existing bilingual homepage, or the project's website configuration. Figures
 * are labeled as planning targets and no achievements, partners, donors or
 * contact details are invented. Existing non-empty values are preserved; only
 * known legacy defaults are replaced with the current homepage wording.
 */
final class HomepageContentSeeder extends Seeder
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function run(): void
    {
        foreach ($this->sections() as $key => $definition) {
            $section = HomepageSection::query()->firstOrCreate(
                ['key' => $key],
                [
                    'type' => $definition['type'],
                    'is_enabled' => true,
                    'display_order' => $definition['order'],
                ],
            );

            foreach (self::LOCALES as $locale) {
                $this->seedTranslation(
                    $section,
                    $locale,
                    $definition['translations'][$locale],
                    $definition['legacy'][$locale] ?? [],
                );
            }

            foreach ($definition['items'] ?? [] as $itemKey => $itemDefinition) {
                $this->seedInlineItem($section, $itemKey, $itemDefinition);
            }
        }
    }

    /**
     * @param  array<string, string|null>  $values
     * @param  array<string, string|null>  $legacyValues
     */
    private function seedTranslation(HomepageSection $section, string $locale, array $values, array $legacyValues): void
    {
        $translation = $section->translations()->firstOrNew(['locale' => $locale]);

        foreach ($values as $field => $value) {
            $current = $translation->getAttribute($field);
            $isKnownDefault = filled($current)
                && array_key_exists($field, $legacyValues)
                && $current === $legacyValues[$field];

            if (filled($current) && ! $isKnownDefault) {
                continue;
            }

            if (filled($value)) {
                $translation->setAttribute($field, $value);
            }
        }

        if ($translation->isDirty()) {
            $translation->save();
        }
    }

    /**
     * @param  array{type: string, order: int, value?: float|null, translations: array<string, array<string, string|null>>}  $definition
     */
    private function seedInlineItem(HomepageSection $section, string $itemKey, array $definition): void
    {
        $item = $section->items()->firstOrCreate(
            ['item_key' => $itemKey],
            [
                'item_type' => 'inline',
                'item_id' => null,
                'is_published' => true,
                'numeric_value' => $definition['value'] ?? null,
                'sort_order' => $definition['order'],
            ],
        );

        if ($item->numeric_value === null && filled($definition['value'] ?? null)) {
            $item->forceFill(['numeric_value' => $definition['value']])->save();
        }

        foreach (self::LOCALES as $locale) {
            $translation = $item->translations()->firstOrNew(['locale' => $locale]);

            foreach ($definition['translations'][$locale] ?? [] as $field => $value) {
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
     * @return array<string, array{type: string, order: int, translations: array<string, array<string, string|null>>, legacy?: array<string, array<string, string|null>>, items?: array<string, array{type: string, order: int, value?: float|null, translations: array<string, array<string, string|null>>}>}>
     */
    private function sections(): array
    {
        $sloganNote = [
            'ar' => 'نعمل بروح الشراكة والمشاركة المجتمعية لتنسيق الاستجابة الإنسانية، وتمكين الأسر والفئات الأكثر هشاشة، والمساهمة في بناء مجتمع فلسطيني أكثر قدرة على الصمود والتعافي، انطلاقاً من شعارنا: من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة.',
            'en' => 'Through partnership and community participation, we coordinate humanitarian response, empower the most vulnerable families and groups, and contribute to a Palestinian society better able to withstand crises and recover, guided by our strategic slogan: from humanitarian response to recovery and sustainable development.',
        ];

        return [
            'hero' => [
                'type' => 'hero',
                'order' => 0,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'العمل التشاركي · قطاع غزة',
                        'title' => "العمل التشاركي\nفي الأزمات والكوارث",
                        'body' => 'نعمل على تنسيق الجهود الإنسانية وتعزيز المشاركة المجتمعية، من أجل استجابة أكثر فاعلية وتعافٍ أكثر استدامة، يحفظ كرامة الإنسان ويعزز قدرة المجتمعات على الصمود.',
                        'cta_label' => 'تعرّف على عملنا',
                        'cta_link' => '/ar/about',
                    ],
                    'en' => [
                        'eyebrow' => 'Participatory action · Gaza',
                        'title' => "From Humanitarian Response\nto Recovery and Sustainable Development",
                        'body' => 'We coordinate humanitarian efforts and strengthen community participation for a more effective response and a more lasting recovery — one that protects human dignity and builds the resilience of communities.',
                        'cta_label' => 'Discover our work',
                        'cta_link' => '/en/about',
                    ],
                ],
                'items' => [
                    'primary' => [
                        'type' => 'inline',
                        'order' => 0,
                        'translations' => [
                            'ar' => ['label' => 'تعرّف على عملنا', 'url' => '/ar/about'],
                            'en' => ['label' => 'Discover our work', 'url' => '/en/about'],
                        ],
                    ],
                    'secondary' => [
                        'type' => 'inline',
                        'order' => 1,
                        'translations' => [
                            'ar' => ['label' => 'كن شريكاً في الأثر', 'url' => '/ar/partners'],
                            'en' => ['label' => 'Be a partner in impact', 'url' => '/en/partners'],
                        ],
                    ],
                ],
            ],
            'intro' => [
                'type' => 'intro',
                'order' => 1,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'من نحن',
                        'title' => 'الإنسان أولاً... والمجتمع شريك في التغيير',
                        'body' => $sloganNote['ar'],
                        'cta_label' => 'اقرأ المزيد',
                        'cta_link' => '/ar/about',
                    ],
                    'en' => [
                        'eyebrow' => 'Who we are',
                        'title' => 'People First — and Communities as Partners in Change',
                        'body' => $sloganNote['en'],
                        'cta_label' => 'Read more',
                        'cta_link' => '/en/about',
                    ],
                ],
                'legacy' => [
                    'ar' => [
                        'eyebrow' => 'عن الجمعية',
                        'title' => 'تعريف مؤسسي',
                        'body' => (string) config('website.vision.ar'),
                        'cta_link' => '/ar/about',
                    ],
                    'en' => [
                        'eyebrow' => 'About PCDR',
                        'title' => 'Institutional profile',
                        'body' => (string) config('website.vision.en'),
                        'cta_link' => '/en/about',
                    ],
                ],
                'items' => [
                    'vision' => [
                        'type' => 'inline',
                        'order' => 0,
                        'translations' => [
                            'ar' => ['title' => 'الرؤية', 'body' => (string) config('website.vision.ar')],
                            'en' => ['title' => 'Vision', 'body' => (string) config('website.vision.en')],
                        ],
                    ],
                    'mission' => [
                        'type' => 'inline',
                        'order' => 1,
                        'translations' => [
                            'ar' => ['title' => 'الرسالة', 'body' => (string) config('website.mission.ar')],
                            'en' => ['title' => 'Mission', 'body' => (string) config('website.mission.en')],
                        ],
                    ],
                ],
            ],
            'areas' => [
                'type' => 'areas',
                'order' => 2,
                'translations' => [
                    'ar' => [
                        'eyebrow' => '١١ محوراً استراتيجياً',
                        'title' => 'مجالات عملنا',
                        'body' => 'المحاور الاستراتيجية للخطة 2027–2029 تنظّم عمل الجمعية وبرامجها المخططة.',
                        'cta_label' => 'عرض الكل',
                        'cta_link' => '/ar/programs',
                    ],
                    'en' => [
                        'eyebrow' => '11 strategic pillars',
                        'title' => 'Our Areas of Work',
                        'body' => 'The strategic pillars of the 2027–2029 plan organize the association’s work and planned programmes.',
                        'cta_label' => 'View all',
                        'cta_link' => '/en/programs',
                    ],
                ],
                'legacy' => [
                    'ar' => [
                        'eyebrow' => 'الاتجاه الاستراتيجي',
                        'title' => 'الاتجاه الاستراتيجي',
                        'body' => 'الأرقام الواردة من الخطة الاستراتيجية مستهدفات تخطيطية أولية، ولا تمثل نتائج محققة.',
                        'cta_label' => 'عرض الكل',
                        'cta_link' => '/ar/programs',
                    ],
                    'en' => [
                        'eyebrow' => 'Strategic direction',
                        'title' => 'Strategic outlook',
                        'body' => 'Figures from the strategic plan are initial planning targets and do not represent achieved results.',
                        'cta_label' => 'View all',
                        'cta_link' => '/en/programs',
                    ],
                ],
            ],
            'framework' => [
                'type' => 'framework',
                'order' => 3,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'منهجنا الإنساني',
                        'title' => 'رحلة متصلة وليست تدخلاً عابراً',
                        'body' => 'من الاستجابة الإنسانية إلى التعافي والتنمية المستدامة.',
                        'cta_label' => null,
                        'cta_link' => null,
                    ],
                    'en' => [
                        'eyebrow' => 'Our humanitarian approach',
                        'title' => 'A continuous journey, not a one-off intervention',
                        'body' => 'From humanitarian response to recovery and sustainable development.',
                        'cta_label' => null,
                        'cta_link' => null,
                    ],
                ],
                'legacy' => [
                    'ar' => [
                        'eyebrow' => 'من الاستجابة إلى التنمية المستدامة',
                        'title' => 'النهج المؤسسي',
                        'body' => 'تنسيق الجهود الإنسانية وتعزيز الشراكات المحلية والدولية، وتقديم الاستجابات الإغاثية والتنموية المتكاملة.',
                    ],
                    'en' => [
                        'eyebrow' => 'From response to sustainable development',
                        'title' => 'Institutional approach',
                        'body' => 'Coordinating humanitarian efforts, strengthening local and international partnerships and delivering integrated relief and development responses.',
                    ],
                ],
                'items' => [
                    'response' => [
                        'type' => 'inline',
                        'order' => 0,
                        'translations' => [
                            'ar' => ['title' => 'الاستجابة الإنسانية', 'body' => 'تلبية الاحتياجات العاجلة بسرعة وتنظيم.'],
                            'en' => ['title' => 'Emergency Response', 'body' => 'Meeting urgent needs quickly and in an organized way.'],
                        ],
                    ],
                    'recovery' => [
                        'type' => 'inline',
                        'order' => 1,
                        'translations' => [
                            'ar' => ['title' => 'التعافي', 'body' => 'استعادة مصادر الرزق والسكن والخدمات الأساسية.'],
                            'en' => ['title' => 'Recovery', 'body' => 'Restoring livelihoods, shelter and basic services.'],
                        ],
                    ],
                    'empowerment' => [
                        'type' => 'inline',
                        'order' => 2,
                        'translations' => [
                            'ar' => ['title' => 'التمكين', 'body' => 'تعزيز قدرة الأسر والمجتمعات على مواجهة الأزمات.'],
                            'en' => ['title' => 'Empowerment', 'body' => 'Strengthening the ability of families and communities to face crises.'],
                        ],
                    ],
                    'development' => [
                        'type' => 'inline',
                        'order' => 3,
                        'translations' => [
                            'ar' => ['title' => 'التنمية المستدامة', 'body' => 'مجتمعات منتجة ومعتمدة على ذاتها تصنع مستقبلها.'],
                            'en' => ['title' => 'Sustainable Development', 'body' => 'Productive, self-reliant communities that shape their own future.'],
                        ],
                    ],
                ],
            ],
            'programs' => [
                'type' => 'programs',
                'order' => 4,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'مجالات البرامج المقترحة',
                        'title' => 'البرامج والتدخلات',
                        'body' => 'مجالات عملنا مستمدة من المحاور الاستراتيجية للخطة 2027–2029، وتوجّه استجابتنا الإنسانية وجهود التعافي والتنمية.',
                        'cta_label' => null,
                        'cta_link' => '/ar/programs',
                    ],
                    'en' => [
                        'eyebrow' => 'Proposed program areas',
                        'title' => 'Programs and Interventions',
                        'body' => 'Our areas of work draw on the strategic pillars of the 2027–2029 plan, guiding humanitarian response, recovery and development.',
                        'cta_label' => null,
                        'cta_link' => '/en/programs',
                    ],
                ],
                'items' => $this->inlineList([
                    'ar' => ['الاستجابة للطوارئ', 'الأمن الغذائي', 'سبل العيش والتمكين', 'الحماية والدعم النفسي والاجتماعي', 'الصحة والمياه والإصحاح', 'التعليم والشباب', 'التعافي المبكر والإيواء', 'المشاركة المجتمعية'],
                    'en' => ['Emergency response', 'Food security', 'Livelihoods', 'Protection and psychosocial support', 'Health, water and sanitation', 'Education and youth', 'Early recovery and shelter', 'Community participation'],
                ], 'program-'),
            ],
            'targets' => [
                'type' => 'targets',
                'order' => 5,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'مستهدفات الخطة الاستراتيجية 2027',
                        'title' => 'ما نستهدف الوصول إليه بنهاية 2027',
                        'body' => 'نستهدف هذه المستويات ضمن مسار الخطة الاستراتيجية، ونعمل على تحقيقها وفق الموارد والقدرات المتاحة. مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.',
                        'cta_label' => null,
                        'cta_link' => '/ar/about#vision',
                    ],
                    'en' => [
                        'eyebrow' => 'Strategic Plan Targets 2027',
                        'title' => 'Where we aim to be by the end of 2027',
                        'body' => 'These targets form part of the strategic plan’s path forward, and we work toward them within available resources and capacities. Initial 2027 planning targets, subject to review once available funding and implementation capacity are determined.',
                        'cta_label' => null,
                        'cta_link' => '/en/about#vision',
                    ],
                ],
                'legacy' => [
                    'ar' => [
                        'body' => 'نستهدف هذه المستويات ضمن مسار الخطة الاستراتيجية، ونعمل على تحقيقها وفق الموارد والقدرات المتاحة.',
                    ],
                    'en' => [
                        'body' => 'These targets form part of the strategic plan’s path forward, and we work toward them within available resources and capacities.',
                    ],
                ],
                'items' => [
                    'families' => [
                        'type' => 'inline', 'order' => 0, 'value' => 3000,
                        'translations' => [
                            'ar' => ['title' => 'الأسر المستهدفة بالتدخلات الإنسانية', 'body' => 'الأسر المستهدفة بالتدخلات الإنسانية', 'display_value' => '3,000'],
                            'en' => ['title' => 'Families targeted for humanitarian interventions', 'body' => 'Families targeted for humanitarian interventions', 'display_value' => '3,000'],
                        ],
                    ],
                    'food-security' => [
                        'type' => 'inline', 'order' => 1, 'value' => 1500,
                        'translations' => [
                            'ar' => ['title' => 'الأسر المستهدفة في الأمن الغذائي', 'body' => 'الأسر المستهدفة في الأمن الغذائي', 'display_value' => '1,500'],
                            'en' => ['title' => 'Families targeted for food security support', 'body' => 'Families targeted for food security support', 'display_value' => '1,500'],
                        ],
                    ],
                    'youth' => [
                        'type' => 'inline', 'order' => 2, 'value' => 200,
                        'translations' => [
                            'ar' => ['title' => 'الشباب المستهدف تدريبهم', 'body' => 'الشباب المستهدف تدريبهم', 'display_value' => '200'],
                            'en' => ['title' => 'Young people targeted for training', 'body' => 'Young people targeted for training', 'display_value' => '200'],
                        ],
                    ],
                    'volunteers' => [
                        'type' => 'inline', 'order' => 3, 'value' => 100,
                        'translations' => [
                            'ar' => ['title' => 'المتطوعون المستهدف إشراكهم', 'body' => 'المتطوعون المستهدف إشراكهم', 'display_value' => '100'],
                            'en' => ['title' => 'Volunteers targeted for engagement', 'body' => 'Volunteers targeted for engagement', 'display_value' => '100'],
                        ],
                    ],
                    'small-projects' => [
                        'type' => 'inline', 'order' => 4, 'value' => 50,
                        'translations' => [
                            'ar' => ['title' => 'المشاريع الصغيرة المستهدفة', 'body' => 'المشاريع الصغيرة المستهدفة', 'display_value' => '50'],
                            'en' => ['title' => 'Small projects targeted', 'body' => 'Small projects targeted', 'display_value' => '50'],
                        ],
                    ],
                ],
            ],
            'community' => [
                'type' => 'community',
                'order' => 6,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'المنهج التشاركي',
                        'title' => 'التغيير المستدام يبدأ من المجتمع',
                        'body' => 'يشارك المجتمع في تحديد الاحتياجات وتصميم التدخلات ومتابعة تنفيذها وتقييم أثرها، بما يعزز الشفافية والعدالة ويحد من الازدواجية.',
                        'cta_label' => null,
                        'cta_link' => '/ar/get-involved',
                    ],
                    'en' => [
                        'eyebrow' => 'Participatory approach',
                        'title' => 'Lasting change starts with the community',
                        'body' => 'Communities help identify needs, design interventions, follow their implementation and evaluate their impact — which strengthens transparency and fairness and avoids duplication.',
                        'cta_label' => null,
                        'cta_link' => '/en/get-involved',
                    ],
                ],
                'items' => [
                    'committees' => [
                        'type' => 'inline', 'order' => 0,
                        'translations' => [
                            'ar' => ['title' => 'اللجان المجتمعية', 'body' => 'تحديد الأولويات في الأحياء ومراكز الإيواء.'],
                            'en' => ['title' => 'Community committees', 'body' => 'Set priorities in neighbourhoods and shelters.'],
                        ],
                    ],
                    'volunteers' => [
                        'type' => 'inline', 'order' => 1,
                        'translations' => [
                            'ar' => ['title' => 'المتطوعون', 'body' => 'إسناد ميداني وفق مدونة سلوك.'],
                            'en' => ['title' => 'Volunteers', 'body' => 'Field support under a code of conduct.'],
                        ],
                    ],
                    'initiatives' => [
                        'type' => 'inline', 'order' => 2,
                        'translations' => [
                            'ar' => ['title' => 'المبادرات المحلية', 'body' => 'مبادرات شبابية ونسوية نطمح إلى دعمها.'],
                            'en' => ['title' => 'Local initiatives', 'body' => 'Youth- and women-led efforts we aim to back.'],
                        ],
                    ],
                    'institutional-partners' => [
                        'type' => 'inline', 'order' => 3,
                        'translations' => [
                            'ar' => ['title' => 'الشركاء المؤسسيون', 'body' => 'موارد وخبرات ضمن اتفاقيات واضحة.'],
                            'en' => ['title' => 'Institutional partners', 'body' => 'Resources and expertise in clear agreements.'],
                        ],
                    ],
                ],
            ],
            'partners' => [
                'type' => 'partners',
                'order' => 7,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'الشراكات',
                        'title' => 'معاً نحو استجابة إنسانية أكثر تكاملاً',
                        'body' => $sloganNote['ar'],
                        'cta_label' => null,
                        'cta_link' => '/ar/partners',
                    ],
                    'en' => [
                        'eyebrow' => 'Partnerships',
                        'title' => 'Together toward a more integrated humanitarian response',
                        'body' => $sloganNote['en'],
                        'cta_label' => null,
                        'cta_link' => '/en/partners',
                    ],
                ],
                'items' => $this->inlineList([
                    'ar' => ['المؤسسات الحكومية والمحلية', 'المنظمات الدولية والعربية', 'الجامعات والمراكز البحثية', 'القطاع الخاص والمجتمع المدني'],
                    'en' => ['Government and local institutions', 'International and Arab organisations', 'Universities and research centres', 'Private sector and civil society'],
                ], 'partner-'),
            ],
            'knowledge' => [
                'type' => 'knowledge',
                'order' => 8,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'المعرفة والإعلام',
                        'title' => 'تقارير ودراسات وقصص',
                        'body' => 'آخر التحديثات المنشورة من الأخبار والإصدارات المعتمدة.',
                        'cta_label' => 'لا توجد مواد منشورة بعد.',
                        'cta_link' => '/ar/publications',
                    ],
                    'en' => [
                        'eyebrow' => 'Knowledge and media',
                        'title' => 'Reports, studies and stories',
                        'body' => 'The latest published news and approved publications.',
                        'cta_label' => 'No published items yet.',
                        'cta_link' => '/en/publications',
                    ],
                ],
            ],
            'cta' => [
                'type' => 'cta',
                'order' => 9,
                'translations' => [
                    'ar' => [
                        'eyebrow' => 'شارك معنا',
                        'title' => 'لأن التعافي مسؤولية مشتركة',
                        'body' => 'نؤمن أن العمل الإنساني الأكثر أثراً يبدأ بالتعاون، ويستمر بالمشاركة، ويصنع مستقبلاً أكثر قدرة على الصمود.',
                        'cta_label' => 'تواصل معنا',
                        'cta_link' => '/ar/contact',
                    ],
                    'en' => [
                        'eyebrow' => 'Get involved',
                        'title' => 'Because recovery is a shared responsibility',
                        'body' => 'We believe the most effective humanitarian work begins with cooperation, continues with participation, and builds a future more able to withstand crises.',
                        'cta_label' => 'Contact us',
                        'cta_link' => '/en/contact',
                    ],
                ],
                'legacy' => [
                    'ar' => [
                        'title' => 'إبداء الاهتمام بالمشاركة',
                        'body' => 'تُسجل طلبات المشاركة وتراجع، ولا يُؤكد أي تكليف عبر هذا النموذج.',
                        'cta_label' => 'نموذج إبداء الاهتمام بالتطوع',
                        'cta_link' => '/ar/get-involved',
                    ],
                    'en' => [
                        'title' => 'Expressions of interest',
                        'body' => 'Expressions of interest are recorded and reviewed. No placement is confirmed through this form.',
                        'cta_label' => 'Volunteer interest form',
                        'cta_link' => '/en/get-involved',
                    ],
                ],
                'items' => [
                    'contact' => [
                        'type' => 'inline', 'order' => 0,
                        'translations' => [
                            'ar' => ['label' => 'تواصل معنا', 'url' => '/ar/contact'],
                            'en' => ['label' => 'Contact us', 'url' => '/en/contact'],
                        ],
                    ],
                    'volunteer' => [
                        'type' => 'inline', 'order' => 1,
                        'translations' => [
                            'ar' => ['label' => 'كن متطوعاً', 'url' => '/ar/get-involved'],
                            'en' => ['label' => 'Become a volunteer', 'url' => '/en/get-involved'],
                        ],
                    ],
                    'explore' => [
                        'type' => 'inline', 'order' => 2,
                        'translations' => [
                            'ar' => ['label' => 'استكشف مجالات عملنا', 'url' => '/ar/programs'],
                            'en' => ['label' => 'Explore our areas of work', 'url' => '/en/programs'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{ar: list<string>, en: list<string>}  $labels
     * @return array<string, array{type: string, order: int, translations: array<string, array<string, string|null>>}>
     */
    private function inlineList(array $labels, string $prefix): array
    {
        $items = [];

        foreach ($labels['ar'] as $index => $arabicLabel) {
            $key = $prefix.($index + 1);
            $items[$key] = [
                'type' => 'inline',
                'order' => $index,
                'translations' => [
                    'ar' => ['title' => $arabicLabel, 'label' => $arabicLabel],
                    'en' => ['title' => $labels['en'][$index], 'label' => $labels['en'][$index]],
                ],
            ];
        }

        return $items;
    }
}
