<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageSectionItem;
use Database\Seeders\HomepageContentSeeder;
use Database\Seeders\RedesignHomepageContentSeeder;
use Database\Seeders\SiteSettingsSeeder;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class RedesignHomepageSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SiteSettingsSeeder::class);
        $this->seed(WebsiteSeeder::class);
        $this->seed(HomepageContentSeeder::class);
        $this->seed(RedesignHomepageContentSeeder::class);
    }

    public function test_canonical_sections_are_preserved_and_source_items_are_added(): void
    {
        $this->assertSame([
            'hero', 'intro', 'areas', 'framework', 'programs',
            'targets', 'community', 'partners', 'knowledge', 'cta',
        ], HomepageSection::query()->orderBy('display_order')->pluck('key')->all());

        $this->assertSame(5, $this->itemKeys('hero', 'slide-')->count());
        $this->assertSame(4, $this->itemKeys('hero', 'ticker-')->count());
        $tickers = $this->section('hero')->items()->where('item_key', 'like', 'ticker-%')->get();
        foreach ($tickers as $ticker) {
            $this->assertNull($ticker->t('url', 'ar'), 'Ticker headlines must not invent link targets.');
            $this->assertNull($ticker->t('url', 'en'), 'Ticker headlines must not invent link targets.');
        }
        $this->assertSame(9, HomepageSection::query()->where('key', 'targets')->firstOrFail()->items()->count());
        $this->assertSame(11, $this->itemKeys('intro', 'objective-')->count());
        $this->assertSame(10, $this->itemKeys('intro', 'value-')->count());
        $this->assertSame(11, $this->itemKeys('programs', 'axis-')->count());
        $this->assertSame(5, $this->itemKeys('community', 'album-')->count());
        $this->assertSame(3, $this->itemKeys('community', 'story-')->count());
    }

    public function test_planning_caveat_is_verbatim_and_placeholders_have_no_media(): void
    {
        $targets = $this->section('targets');
        $caveat = 'مستهدفات تخطيطية أولية لعام 2027، تخضع للمراجعة بعد تحديد التمويل المتاح والقدرات التنفيذية.';

        $this->assertStringContainsString($caveat, (string) $targets->t('body', 'ar'));
        $this->assertStringContainsString(
            'Initial 2027 planning targets, subject to review once available funding and implementation capacity are determined.',
            (string) $targets->t('body', 'en'),
        );

        $placeholders = HomepageSectionItem::query()
            ->whereIn('item_key', ['album-1', 'album-2', 'album-3', 'album-4', 'album-5', 'story-1', 'story-2', 'story-3'])
            ->get();

        $this->assertCount(8, $placeholders);
        foreach ($placeholders as $placeholder) {
            $this->assertNull($placeholder->media_id);
            $this->assertNotEmpty($placeholder->t('body', 'ar'));

            if (str_starts_with((string) $placeholder->item_key, 'album-')) {
                $this->assertStringContainsString('لا توجد صورة موثقة بعد', (string) $placeholder->t('body', 'ar'));
            }
        }
    }

    public function test_reseeding_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        $slide = $this->section('hero')->items()->where('item_key', 'slide-1')->firstOrFail();
        $slide->translations()->updateOrCreate(['locale' => 'ar'], ['title' => 'صياغة مخصصة من المسؤول']);

        $before = HomepageSectionItem::query()->count();

        $this->seed(HomepageContentSeeder::class);
        $this->seed(RedesignHomepageContentSeeder::class);

        $this->assertSame($before, HomepageSectionItem::query()->count());
        $this->assertSame('صياغة مخصصة من المسؤول', $slide->fresh()->t('title', 'ar'));
    }

    private function section(string $key): HomepageSection
    {
        return HomepageSection::query()->where('key', $key)->firstOrFail();
    }

    private function itemKeys(string $sectionKey, string $prefix): Collection
    {
        return $this->section($sectionKey)->items()
            ->where('item_key', 'like', $prefix.'%')
            ->pluck('item_key');
    }
}
