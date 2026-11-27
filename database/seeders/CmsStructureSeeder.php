<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use App\Models\WebsiteOrganization;
use App\Models\WebsitePage;
use App\Services\HomepageService;
use Illuminate\Database\Seeder;

/**
 * Seeds the predefined CMS structure: pages, sections and shared identity.
 *
 * The registry is fixed in config/cms_structure.php. Running this seeder never
 * creates arbitrary pages or sections, never overwrites existing visibility
 * choices, and moves legacy inline items into their canonical sections without
 * deleting any content.
 */
final class CmsStructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedSections();
        $this->seedOrganization();
        $this->moveLegacyItems();

        app(HomepageService::class)->flush();
    }

    private function seedPages(): void
    {
        foreach (config('cms_structure.pages', []) as $key => $definition) {
            $page = WebsitePage::query()->firstOrNew(['key' => $key]);

            if (! $page->exists) {
                $page->fill([
                    'path' => $definition['path'],
                    'is_enabled' => true,
                    'display_order' => $definition['order'],
                ]);
                $page->save();
            }

            foreach (config('website.locales', ['ar', 'en']) as $locale) {
                $translation = $page->translations()->firstOrNew(['locale' => $locale]);
                if ($translation->exists) {
                    continue;
                }

                $translation->fill([
                    'name' => $definition['label'][$locale] ?? null,
                    'seo_title' => ($definition['label'][$locale] ?? null).' | '.config('website.short_name.'.$locale, 'PCDR'),
                    'seo_description' => config('website.description.'.$locale),
                ])->save();
            }
        }
    }

    private function seedSections(): void
    {
        foreach (config('cms_structure.sections', []) as $key => $definition) {
            $section = HomepageSection::query()->firstOrNew(['key' => $key]);

            if (! $section->exists) {
                $section->fill([
                    'page_key' => $definition['page'],
                    'type' => $key,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_enabled' => true,
                    'display_order' => $definition['order'],
                ]);
                $section->save();
            } elseif ((string) $section->page_key !== (string) $definition['page']) {
                $section->update(['page_key' => $definition['page']]);
            }
        }
    }

    private function seedOrganization(): void
    {
        $organization = WebsiteOrganization::query()->firstOrCreate([]);

        foreach (config('website.locales', ['ar', 'en']) as $locale) {
            $translation = $organization->translations()->firstOrNew(['locale' => $locale]);
            if ($translation->exists) {
                continue;
            }

            $translation->fill([
                'title' => config('website.name.'.$locale),
                'description' => config('website.description.'.$locale),
                'vision_title' => $locale === 'ar' ? 'رؤيتنا' : 'Our vision',
                'vision' => config('website.vision.'.$locale),
                'mission_title' => $locale === 'ar' ? 'رسالتنا' : 'Our mission',
                'mission' => config('website.mission.'.$locale),
            ])->save();
        }
    }

    private function moveLegacyItems(): void
    {
        $this->moveItems('about', 'vision_mission', fn (HomepageSectionItem $item): bool => in_array($item->item_key, ['mission', 'vision'], true)
            || str_starts_with((string) $item->item_key, 'value-'));
        $this->moveItems('about', 'objectives', fn (HomepageSectionItem $item): bool => str_starts_with((string) $item->item_key, 'objective-'));
        $this->moveItems('programs', 'program_areas', fn (HomepageSectionItem $item): bool => str_starts_with((string) $item->item_key, 'axis-'));
        $this->moveItems('news', 'latest_projects', fn (HomepageSectionItem $item): bool => $item->tab === 'projects'
            || str_starts_with((string) $item->item_key, 'project-'));
    }

    private function moveItems(string $sourceKey, string $targetKey, callable $matches): void
    {
        $source = HomepageSection::query()->where('key', $sourceKey)->first();
        $target = HomepageSection::query()->where('key', $targetKey)->first();

        if ($source === null || $target === null) {
            return;
        }

        $source->items->filter($matches)->each(
            fn (HomepageSectionItem $item) => $item->update(['homepage_section_id' => $target->id]),
        );
    }
}
