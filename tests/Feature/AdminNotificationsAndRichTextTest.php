<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Services\TranslationSaver;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminNotificationsAndRichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shares_normalized_flash_payloads(): void
    {
        $this->withSession([
            'success' => 'Saved directly',
            'error' => 'Failed directly',
            'flash' => [
                'success' => 'Saved nested',
                'warning' => 'Review this',
                'workflow' => ['type' => 'invalid_transition'],
            ],
        ])->get('/ar')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', 'Saved directly')
            ->where('flash.error', 'Failed directly')
            ->where('flash.warning', 'Review this')
            ->where('flash.workflow.type', 'invalid_transition'));
    }

    public function test_rich_text_renderer_strips_dangerous_markup_and_keeps_editor_markup(): void
    {
        $clean = RichText::render(
            '<p style="text-align: center; color: red" class="safe-class evil()">'
            .'<a href="JaVaScRiPt:alert(1)">bad link</a>'
            .'<a href="https://example.test/path" target="_blank" rel="nofollow">good link</a>'
            .'<img src="data:image/svg+xml;base64,PHN2Zz4=" onerror="alert(1)" width="320" alt="photo">'
            .'</p><svg><script>alert(2)</script></svg><iframe src="https://example.test">blocked</iframe>'
            .'<h2 style="text-align: right">عنوان</h2>'
            .'<ul><li>أول</li><li>ثانٍ</li></ul>'
            .'<blockquote><strong>قوي</strong> <em>مائل</em> <u>تحته</u> <s>مشطوب</s> '
            .'<mark>تمييز</mark> <sub>سفلي</sub> <sup>علوي</sup> <code>كود</code></blockquote>'
            .'<figure><img src="/storage/photo.png" alt="صورة" width="320"><figcaption>وصف</figcaption></figure>'
            .'<hr>',
        );

        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertStringNotContainsString('data:image', strtolower($clean));
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('color: red', $clean);
        $this->assertStringNotContainsString('evil(', $clean);
        $this->assertStringNotContainsString('<svg', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('blocked', $clean);
        $this->assertStringContainsString('text-align: center', $clean);
        $this->assertStringContainsString('href="https://example.test/path"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('<h2 style="text-align: right">عنوان</h2>', $clean);
        $this->assertStringContainsString('<li>أول</li>', $clean);
        $this->assertStringContainsString('<blockquote>', $clean);
        $this->assertStringContainsString('<strong>قوي</strong>', $clean);
        $this->assertStringContainsString('<mark>تمييز</mark>', $clean);
        $this->assertStringContainsString('<sub>سفلي</sub>', $clean);
        $this->assertStringContainsString('<sup>علوي</sup>', $clean);
        $this->assertStringContainsString('src="/storage/photo.png"', $clean);
        $this->assertStringContainsString('<figcaption>وصف</figcaption>', $clean);
        $this->assertStringContainsString('<hr>', $clean);
    }

    public function test_translation_saver_round_trips_sanitized_editor_html(): void
    {
        $page = Page::create([
            'status' => ContentStatus::Draft->value,
            'display_order' => 0,
        ]);

        app(TranslationSaver::class)->sync(
            $page,
            [
                'ar' => [
                    'title' => 'صفحة المحرر',
                    'slug' => 'editor-page',
                    'body' => '<h3>عنوان</h3><p dir="rtl" onmouseover="alert(1)"><strong>نص</strong><a href="/ar/about">رابط</a></p>',
                ],
            ],
            ['title', 'slug', 'summary', 'body'],
            'page_id',
        );

        $translation = PageTranslation::where('page_id', $page->getKey())
            ->where('locale', 'ar')
            ->firstOrFail();

        $this->assertStringContainsString('<h3>عنوان</h3>', $translation->body);
        $this->assertStringContainsString('<strong>نص</strong>', $translation->body);
        $this->assertStringContainsString('href="/ar/about"', $translation->body);
        $this->assertStringNotContainsString('onmouseover', $translation->body);
        $this->assertStringNotContainsString('dir=', $translation->body);
    }
}
