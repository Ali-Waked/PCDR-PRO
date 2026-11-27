<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\StrategicPillar;
use App\Services\NavigationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Structural navigation and strategic-pillar reference data.
 *
 * Titles are taken from config/website.php, which transcribes the approved
 * strategic plan. English wording is a working translation, not a formally
 * approved translation. No people, partners, projects, results or contact
 * details are invented. Existing administrator-managed values are preserved.
 */
final class WebsiteSeeder extends Seeder
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedNavigation();
            $this->seedPillars();
        });

        app(NavigationService::class)->flush();
    }

    private function seedNavigation(): void
    {
        // Existing installations keep their navigation rows and translations;
        // only the structural key changes so old children resolve to the new
        // independent pages rather than aggregate-page fragments.
        $this->migrateLegacyChildLinks();

        $items = [
            [
                'key' => 'home', 'ar' => 'الرئيسية', 'en' => 'Home', 'children' => [],
            ],
            [
                'key' => 'about', 'ar' => 'عن الجمعية', 'en' => 'About PCDR',
                'children' => [
                    ['key' => 'about_vision', 'ar' => 'رؤيتنا', 'en' => 'Our vision'],
                    ['key' => 'about_objectives', 'ar' => 'أهدافنا الاستراتيجية', 'en' => 'Our strategic objectives'],
                    ['key' => 'about_achievements', 'ar' => 'إنجازاتنا بالأرقام', 'en' => 'Our achievements in numbers'],
                    ['key' => 'about_board', 'ar' => 'مجلس الإدارة', 'en' => 'Board of directors'],
                ],
            ],
            [
                'key' => 'programs', 'ar' => 'برامجنا ومشاريعنا', 'en' => 'Programs & projects', 'children' => [],
            ],
            [
                'key' => 'media', 'ar' => 'المركز الإعلامي', 'en' => 'Media centre',
                'children' => [
                    ['key' => 'media_news', 'ar' => 'الأخبار والأنشطة', 'en' => 'News & activities'],
                    ['key' => 'media_albums', 'ar' => 'ألبوم الصور', 'en' => 'Photo album'],
                    ['key' => 'media_stories', 'ar' => 'قصص نجاح', 'en' => 'Success stories'],
                ],
            ],
            [
                'key' => 'partners', 'ar' => 'شركاؤنا', 'en' => 'Our partners', 'children' => [],
            ],
            [
                'key' => 'contact', 'ar' => 'اتصل بنا', 'en' => 'Contact us', 'children' => [],
            ],
            [
                'key' => 'complaints', 'ar' => 'صندوق الشكاوى', 'en' => 'Complaints', 'children' => [],
            ],
        ];

        foreach (['primary' => 'Primary menu', 'footer' => 'Footer menu'] as $key => $label) {
            $menu = NavigationMenu::query()->firstOrCreate(['key' => $key], ['label' => $label]);

            foreach ($items as $order => $item) {
                $root = $this->seedMenuItem($menu, null, $item, $order);

                foreach ($item['children'] as $childOrder => $child) {
                    $this->seedMenuItem($menu, $root, $child, $childOrder);
                }
            }
        }

        // The parent rows remain dropdown labels only; the removed aggregate
        // landing links are kept as disabled history instead of being shown.
        $groupParentIds = NavigationItem::query()
            ->whereNull('parent_id')
            ->whereIn('link_value', ['about', 'media'])
            ->pluck('id');
        NavigationItem::query()
            ->whereIn('parent_id', $groupParentIds)
            ->where('link_type', 'internal')
            ->whereIn('link_value', ['about', 'media'])
            ->update(['is_enabled' => false]);

        // Older seeds exposed retired pages. Keep their rows for history, but
        // stop presenting them in the public menus.
        NavigationItem::query()
            ->whereHas('menu', fn ($query) => $query->whereIn('key', ['primary', 'footer']))
            ->where('link_type', 'internal')
            ->whereNull('parent_id')
            ->whereIn('link_value', ['pillars', 'news', 'publications', 'transparency', 'partnerships', 'involve'])
            ->update(['is_enabled' => false]);
    }

    private function migrateLegacyChildLinks(): void
    {
        $map = [
            'vision' => 'about_vision',
            'objectives' => 'about_objectives',
            'achievements' => 'about_achievements',
            'board' => 'about_board',
            'news' => 'media_news',
            'albums' => 'media_albums',
            'stories' => 'media_stories',
        ];
        $groupParentIds = NavigationItem::query()
            ->whereNull('parent_id')
            ->whereIn('link_value', ['about', 'media'])
            ->pluck('id');

        foreach ($map as $legacyKey => $newKey) {
            NavigationItem::query()
                ->whereIn('parent_id', $groupParentIds)
                ->where('link_type', 'internal')
                ->where('link_value', $legacyKey)
                ->get()
                ->each(function (NavigationItem $item) use ($newKey): void {
                    $duplicate = NavigationItem::query()
                        ->where('navigation_menu_id', $item->navigation_menu_id)
                        ->where('parent_id', $item->parent_id)
                        ->where('link_type', 'internal')
                        ->where('link_value', $newKey)
                        ->whereKeyNot($item->id)
                        ->exists();

                    $item->update($duplicate
                        ? ['is_enabled' => false]
                        : ['link_value' => $newKey, 'is_enabled' => true]);
                });
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedMenuItem(NavigationMenu $menu, ?NavigationItem $parent, array $definition, int $order): NavigationItem
    {
        $record = NavigationItem::query()->firstOrCreate(
            [
                'navigation_menu_id' => $menu->id,
                'parent_id' => $parent?->id,
                'link_type' => 'internal',
                'link_value' => $definition['key'],
            ],
            [
                'is_enabled' => true,
                'display_order' => $order,
            ],
        );

        foreach (self::LOCALES as $locale) {
            $translation = $record->translations()->firstOrNew(['locale' => $locale]);

            if (filled($translation->label)) {
                continue;
            }

            $translation->forceFill(['label' => $definition[$locale]])->save();
        }

        return $record;
    }

    private function seedPillars(): void
    {
        $objectives = $this->strategicObjectives();

        foreach (config('website.pillars', []) as $order => $definition) {
            $code = (string) $definition['code'];
            $pillar = StrategicPillar::query()->firstOrCreate(
                ['code' => $code],
                [
                    'status' => ContentStatus::Published,
                    'published_at' => now(),
                    'display_order' => $order + 1,
                    'is_featured' => $order < 6,
                ],
            );

            $slug = Str::slug($code.'-'.$definition['en']);

            foreach (self::LOCALES as $locale) {
                $translation = $pillar->translations()->firstOrNew(['locale' => $locale]);
                $values = [
                    'slug' => $slug,
                    'title' => $definition[$locale],
                    'short_title' => $definition[$locale],
                    'description' => $objectives[$code][$locale],
                    'objectives' => $objectives[$code][$locale],
                    'seo_title' => $definition[$locale],
                    'seo_description' => $objectives[$code][$locale],
                ];

                foreach ($values as $field => $value) {
                    if (filled($translation->getAttribute($field))) {
                        continue;
                    }

                    $translation->setAttribute($field, $value);
                }

                if ($translation->isDirty()) {
                    $translation->save();
                }
            }
        }
    }

    /**
     * Strategic objectives transcribed from the approved plan (chapter two).
     * English values are faithful working translations for the bilingual site.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    private function strategicObjectives(): array
    {
        return [
            '01' => [
                'ar' => 'بناء منظومة استجابة إنسانية سريعة وفعالة ومنظمة للتعامل مع الأزمات والكوارث، وتلبية الاحتياجات الأساسية للأسر المتضررة.',
                'en' => 'Build a fast, effective and organised humanitarian response system for crises and disasters, and meet the basic needs of affected families.',
            ],
            '02' => [
                'ar' => 'تعزيز الأمن الغذائي للأسر الفقيرة والمتضررة وضمان وصول المساعدات الغذائية بصورة عادلة ومنتظمة.',
                'en' => 'Strengthen food security for poor and affected families and ensure fair and regular access to food assistance.',
            ],
            '03' => [
                'ar' => 'الانتقال بالأسر من الاعتماد المستمر على المساعدات إلى تعزيز القدرة على الإنتاج وتحقيق دخل مستدام.',
                'en' => 'Move families from continued dependence on assistance toward productive capacity and sustainable income.',
            ],
            '04' => [
                'ar' => 'تقديم خدمات حماية ورعاية متكاملة للفئات الأكثر هشاشة، وتعزيز التماسك الاجتماعي والقدرة على تجاوز آثار الأزمات.',
                'en' => 'Provide integrated protection and care services for the most vulnerable groups and strengthen social cohesion and the ability to overcome the effects of crises.',
            ],
            '05' => [
                'ar' => 'تحسين الظروف الصحية والبيئية للأسر والمجتمعات المتضررة والحد من المخاطر الصحية الناتجة عن الأزمات والكوارث.',
                'en' => 'Improve the health and environmental conditions of affected families and communities and reduce health risks arising from crises and disasters.',
            ],
            '06' => [
                'ar' => 'الحد من الآثار التعليمية للأزمات وتمكين الأطفال والشباب من اكتساب المهارات والمعارف اللازمة للمستقبل.',
                'en' => 'Reduce the educational effects of crises and enable children and young people to acquire the skills and knowledge needed for the future.',
            ],
            '07' => [
                'ar' => 'المساهمة في تحسين ظروف السكن والتعافي المجتمعي وتعزيز قدرة الأسر المتضررة على استعادة حياتها الطبيعية.',
                'en' => 'Contribute to improving housing conditions and community recovery and strengthen the ability of affected families to restore normal life.',
            ],
            '08' => [
                'ar' => 'بناء قاعدة مجتمعية واسعة تشارك في تحديد الاحتياجات وتنفيذ البرامج ومراقبة أثرها.',
                'en' => 'Build a broad community base that participates in identifying needs, implementing programmes and monitoring their impact.',
            ],
            '09' => [
                'ar' => 'تطوير قدرة الجمعية على الوصول إلى مصادر التمويل وتعزيز حضورها المؤسسي محلياً ودولياً.',
                'en' => "Develop the organisation's ability to access funding sources and strengthen its institutional presence locally and internationally.",
            ],
            '10' => [
                'ar' => 'تعزيز حضور الجمعية وإبراز رسالتها الإنسانية وتوثيق احتياجات المجتمع ونتائج التدخلات وتطوير التواصل مع الجمهور والشركاء.',
                'en' => "Strengthen the organisation's presence, communicate its humanitarian message, document community needs and intervention results, and develop communication with the public and partners.",
            ],
            '11' => [
                'ar' => 'بناء قرارات الجمعية وبرامجها على معلومات موثوقة واحتياجات فعلية وتعزيز قدرتها على تحليل الأزمات وتحديد التدخلات ذات الأولوية.',
                'en' => "Base the organisation's decisions and programmes on reliable information and actual needs and strengthen its ability to analyse crises and identify priority interventions.",
            ],
        ];
    }
}
