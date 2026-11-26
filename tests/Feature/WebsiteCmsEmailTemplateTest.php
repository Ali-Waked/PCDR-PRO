<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InquiryReplyMail;
use App\Services\AccessManagementService;
use App\Services\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Tests\TestCase;

final class WebsiteCmsEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'template-editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);

        $this->withoutVite();
    }

    public function test_template_management_requires_explicit_permissions(): void
    {
        $viewer = $this->userWith(['email_templates.view']);
        $this->actingAs($viewer)->get(route('cms.email-templates.index', ['locale' => self::LOCALE]))->assertOk();

        $creator = $this->userWith(['email_templates.create']);
        $unauthorized = $this->userWith(['pillars.view']);

        $this->actingAs($creator)->get(route('cms.email-templates.index', ['locale' => self::LOCALE]))->assertForbidden();
        $this->actingAs($unauthorized)->post(
            route('cms.email-templates.store', ['locale' => self::LOCALE]),
            $this->payload('unauthorized_template'),
        )->assertForbidden();
    }

    public function test_templates_can_be_created_updated_and_deleted_with_audit(): void
    {
        $editor = $this->bootstrapUser();

        $this->actingAs($editor)->post(
            route('cms.email-templates.store', ['locale' => self::LOCALE]),
            $this->payload('Bad-Key'),
        )->assertSessionHasErrors('key');

        $this->post(route('cms.email-templates.store', ['locale' => self::LOCALE]), $this->payload('custom_followup'))
            ->assertRedirect()
            ->assertSessionHas('flash.success');

        $template = EmailTemplate::query()->where('key', 'custom_followup')->firstOrFail();

        $this->assertSame(2, $template->translations()->count());
        $this->assertSame('متابعة مخصصة', $template->translationFor(self::LOCALE)->name);
        $this->assertDatabaseHas('email_template_translations', [
            'email_template_id' => $template->id,
            'locale' => 'en',
            'subject' => 'Custom follow-up {{ inquiry_reference }}',
        ]);

        $this->put(route('cms.email-templates.update', ['locale' => self::LOCALE, 'template' => $template->id]), [
            'is_active' => false,
            'sort_order' => 5,
            'translations' => [
                'ar' => ['name' => 'متابعة محدثة', 'subject' => 'متابعة {{ inquiry_reference }}', 'body' => 'يرجى الرد على {{ inquiry_reference }}'],
                'en' => ['name' => 'Updated follow-up', 'subject' => 'Updated {{ inquiry_reference }}', 'body' => 'Please reply to {{ inquiry_reference }}'],
            ],
        ])->assertRedirect();

        $template->refresh();

        $this->assertFalse($template->is_active);
        $this->assertSame(5, $template->sort_order);
        $this->assertSame('متابعة محدثة', $template->translationFor(self::LOCALE)->name);

        $this->delete(route('cms.email-templates.destroy', ['locale' => self::LOCALE, 'template' => $template->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_templates.create']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_templates.update']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_templates.delete']);
    }

    public function test_preview_interpolates_only_allowlisted_variables_and_never_evaluates_code(): void
    {
        $response = $this->actingAs($this->bootstrapUser())->postJson(
            route('cms.email-templates.preview', ['locale' => self::LOCALE]),
            [
                'subject' => 'Re: {{ inquiry_reference }} / {{ unknown_variable }}',
                'body' => 'Hello {{ sender_name }} — {{ 7*7 }} — {{ system("id") }}',
            ],
        )->assertOk();

        $body = (string) $response->json('body');

        $this->assertSame('Re: 01JPREVIEW00000000000000000 / ', $response->json('subject'));
        $this->assertStringContainsString((string) __('website.cms.preview_sender_name'), $body);
        $this->assertStringNotContainsString('49', $body);
        $this->assertStringNotContainsString('uid=', $body);

        $rendered = app(TemplateRenderer::class)->render(
            '{{ sender_name }}',
            ['sender_name' => '<script>alert("xss")</script>'],
            false,
        );

        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_index_provides_paginated_filtered_management_props(): void
    {
        $editor = $this->bootstrapUser();

        foreach (range(1, 13) as $index) {
            $this->createTemplate('filter_template_'.$index, $index % 2 === 0, $index);
        }

        $this->actingAs($editor)->get(route('cms.email-templates.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/EmailTemplates/Index')
                ->where('templates.meta.total', 13)
                ->where('templates.meta.current_page', 1)
                ->where('templates.meta.per_page', 12)
                ->where('templates.meta.last_page', 2)
                ->has('templates.data', 12)
                ->where('stats.total', 13)
                ->where('stats.active', 6)
                ->where('stats.inactive', 7)
                ->where('filters.q', '')
                ->where('variableCatalog.sender_name.key', 'sender_name')
                ->where('variableCatalog.sender_name.label', __('email_templates.variables_sender_name_label', [], self::LOCALE))
                ->where('starters.0.key', 'inquiry_received')
                ->has('guideExample'));

        $this->get(route('cms.email-templates.index', ['locale' => self::LOCALE, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.meta.current_page', 2)
                ->has('templates.data', 1));

        $this->get(route('cms.email-templates.index', ['locale' => self::LOCALE, 'q' => 'filter_template_13']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.meta.total', 1)
                ->where('templates.data.0.key', 'filter_template_13')
                ->where('filters.q', 'filter_template_13'));

        $this->get(route('cms.email-templates.index', ['locale' => self::LOCALE, 'status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('templates.meta.total', 6));

        EmailTemplate::query()->where('key', 'filter_template_13')
            ->firstOrFail()
            ->translations()
            ->where('locale', 'ar')
            ->delete();

        $this->get(route('cms.email-templates.index', ['locale' => self::LOCALE, 'locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('templates.meta.total', 13));
    }

    public function test_preview_renders_sanitized_html_with_sample_data_and_sends_no_mail(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->bootstrapUser())->postJson(
            route('cms.email-templates.preview', ['locale' => 'en']),
            [
                'locale' => 'en',
                'subject' => 'Re: {{ inquiry_reference }}',
                'body' => '<p onclick="alert(1)">Hello {{ sender_name }}</p>'
                    .'<script>alert("xss")</script>'
                    .'<a href="javascript:alert(1)">unsafe</a>'
                    .'<a href="https://example.test">safe</a>'
                    .'<img src="x" onerror="alert(1)">',
            ],
        )->assertOk();

        $html = (string) $response->json('html');

        $this->assertSame('Re: 01JPREVIEW00000000000000000', $response->json('subject'));
        $this->assertSame('ltr', $response->json('direction'));
        $this->assertSame('en', $response->json('locale'));
        $this->assertSame('recipient@example.test', $response->json('recipient'));
        $this->assertSame('01JPREVIEW00000000000000000', $response->json('sampleVariables.inquiry_reference'));
        $this->assertTrue((bool) $response->json('isDraft'));
        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html);
        $this->assertStringContainsString('<p>Hello ', $html);
        $this->assertStringContainsString('https://example.test', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString((string) __('website.cms.preview_sender_name', [], 'en'), $response->json('body'));

        Mail::assertNothingSent();
    }

    public function test_preview_can_simulate_blocked_images_without_sending_mail(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->bootstrapUser())->postJson(
            route('cms.email-templates.preview', ['locale' => self::LOCALE]),
            [
                'locale' => self::LOCALE,
                'subject' => 'Subject {{ inquiry_reference }}',
                'body' => 'Body',
                'images_blocked' => true,
            ],
        )->assertOk();

        $html = (string) $response->json('html');

        $this->assertStringContainsString('data-email-logo-fallback="true"', $html);
        $this->assertStringContainsString('شعار', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('data:image', $html);

        Mail::assertNothingSent();
    }

    public function test_send_test_delivers_through_the_real_cid_path(): void
    {
        config()->set('email.brand.logo_mode', 'cid');

        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent): void {
            $sent[] = $event->message;
        });

        $editor = $this->bootstrapUser();

        $response = $this->actingAs($editor)->postJson(
            route('cms.email-templates.send-test', ['locale' => self::LOCALE]),
            [
                'locale' => self::LOCALE,
                'subject' => 'Test {{ inquiry_reference }}',
                'body' => 'Test body',
            ],
        )->assertOk();

        $this->assertTrue((bool) $response->json('success'));
        $this->assertSame($editor->email, $response->json('recipient'));
        $this->assertSame(self::LOCALE, $response->json('locale'));
        $this->assertCount(1, $sent);

        $message = $sent[0];
        $this->assertInstanceOf(SymfonyEmail::class, $message);

        $html = (string) $message->getHtmlBody();

        $this->assertStringContainsString('data-pcdr-email="layout-v1"', $html);
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringNotContainsString('cid:pcdr-email-logo@pcdr.local', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('data-email-preview="true"', $html);
        $this->assertCount(1, $message->getAttachments());
        $this->assertSame('email-logo.png', $message->getAttachments()[0]->getFilename());
        $this->assertStringContainsString('Test body', (string) $message->getTextBody());
    }

    public function test_preview_supports_arabic_direction_and_saved_or_draft_sources(): void
    {
        $editor = $this->bootstrapUser();
        $template = $this->createTemplate('saved_preview');

        $template->translations()->where('locale', self::LOCALE)->update([
            'name' => 'معاينة محفوظة',
            'subject' => 'محفوظ {{ inquiry_reference }}',
            'body' => 'محتوى محفوظ',
        ]);

        $saved = $this->actingAs($editor)->postJson(
            route('cms.email-templates.preview', ['locale' => self::LOCALE]),
            ['locale' => self::LOCALE, 'template_id' => $template->id],
        )->assertOk();

        $this->assertSame('محفوظ 01JPREVIEW00000000000000000', $saved->json('subject'));
        $this->assertSame('محتوى محفوظ', $saved->json('body'));
        $this->assertFalse((bool) $saved->json('isDraft'));
        $this->assertSame($template->id, $saved->json('templateId'));
        $this->assertSame('rtl', $saved->json('direction'));
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', (string) $saved->json('html'));

        $draft = $this->postJson(
            route('cms.email-templates.preview', ['locale' => self::LOCALE]),
            [
                'locale' => self::LOCALE,
                'template_id' => $template->id,
                'subject' => 'مسودة {{ inquiry_reference }}',
                'body' => 'محتوى المسودة',
            ],
        )->assertOk();

        $this->assertSame('مسودة 01JPREVIEW00000000000000000', $draft->json('subject'));
        $this->assertSame('محتوى المسودة', $draft->json('body'));
        $this->assertTrue((bool) $draft->json('isDraft'));
    }

    public function test_duplicate_and_status_actions_are_validated_and_authorized(): void
    {
        $editor = $this->bootstrapUser();
        $source = $this->createTemplate('source_template');

        $this->actingAs($editor)->post(
            route('cms.email-templates.duplicate', ['locale' => self::LOCALE, 'template' => $source->id]),
            ['key' => 'source_custom_copy'],
        )->assertRedirect()->assertSessionHas('flash.success');

        $this->assertDatabaseHas('email_templates', ['key' => 'source_custom_copy']);

        $this->post(
            route('cms.email-templates.duplicate', ['locale' => self::LOCALE, 'template' => $source->id]),
            ['key' => 'source_template'],
        )->assertSessionHasErrors('key');

        $this->patch(
            route('cms.email-templates.status', ['locale' => self::LOCALE, 'template' => $source->id]),
            ['is_active' => false],
        )->assertRedirect();

        $this->assertFalse((bool) $source->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_templates.duplicate']);

        $viewer = $this->userWith(['email_templates.view']);
        $unauthorized = $this->userWith(['pillars.view']);

        $this->actingAs($viewer)->patch(
            route('cms.email-templates.status', ['locale' => self::LOCALE, 'template' => $source->id]),
            ['is_active' => true],
        )->assertForbidden();

        $this->actingAs($unauthorized)->postJson(
            route('cms.email-templates.preview', ['locale' => self::LOCALE]),
            ['subject' => 'Subject', 'body' => 'Body'],
        )->assertForbidden();

        $this->actingAs($viewer)->post(
            route('cms.email-templates.duplicate', ['locale' => self::LOCALE, 'template' => $source->id]),
        )->assertForbidden();
    }

    public function test_wizard_save_sanitizes_html_but_preserves_plain_text_templates(): void
    {
        $editor = $this->bootstrapUser();
        $payload = $this->payload('wizard_template');
        $payload['translations']['ar']['body'] = '<p>مرحبا <strong>بك</strong></p><script>alert("xss")</script>';
        $payload['translations']['en']['body'] = "Plain line one\nPlain line two";

        $this->actingAs($editor)->post(
            route('cms.email-templates.store', ['locale' => self::LOCALE]),
            $payload,
        )->assertRedirect();

        $template = EmailTemplate::query()->where('key', 'wizard_template')->firstOrFail();
        $arabicBody = (string) $template->translationFor(self::LOCALE)->body;
        $englishBody = (string) $template->translationFor('en')->body;

        $this->assertStringContainsString('<strong>بك</strong>', $arabicBody);
        $this->assertStringNotContainsString('<script', $arabicBody);
        $this->assertSame("Plain line one\nPlain line two", $englishBody);

        $this->get(route('cms.email-templates.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.data.0.key', 'wizard_template')
                ->where('templates.data.0.translations.en.body', '<p>Plain line one</p><p>Plain line two</p>'));
    }

    public function test_existing_plain_and_html_bodies_keep_working_in_the_mailable(): void
    {
        $plain = new InquiryReplyMail('Subject', "line one\nline two");
        $plainHtml = $plain->render();

        $this->assertStringContainsString('line one<br />', $plainHtml);
        $this->assertStringContainsString('line two', $plainHtml);
        $this->assertStringNotContainsString('<script', $plainHtml);

        $html = new InquiryReplyMail('Subject', '<p>Hello <strong>there</strong></p><script>alert("xss")</script>');
        $htmlBody = $html->render();

        $this->assertStringContainsString('<strong>there</strong>', $htmlBody);
        $this->assertStringNotContainsString('<script', $htmlBody);
    }

    /** @return array<string, mixed> */
    private function payload(string $key): array
    {
        return [
            'key' => $key,
            'is_active' => true,
            'sort_order' => 0,
            'translations' => [
                'ar' => [
                    'name' => 'متابعة مخصصة',
                    'description' => 'قالب تجريبي',
                    'subject' => 'متابعة {{ inquiry_reference }}',
                    'body' => 'نرجو الرد على {{ inquiry_reference }}',
                ],
                'en' => [
                    'name' => 'Custom follow-up',
                    'description' => 'Test template',
                    'subject' => 'Custom follow-up {{ inquiry_reference }}',
                    'body' => 'Please reply to {{ inquiry_reference }}',
                ],
            ],
        ];
    }

    private function createTemplate(string $key, bool $active = true, int $sortOrder = 0): EmailTemplate
    {
        $template = EmailTemplate::query()->create([
            'key' => $key,
            'is_active' => $active,
            'sort_order' => $sortOrder,
        ]);

        foreach (config('website.locales', ['ar', 'en']) as $code) {
            $template->translations()->create([
                'locale' => $code,
                'name' => 'Test template '.$code,
                'description' => 'Created by the test suite.',
                'subject' => 'Subject {{ inquiry_reference }}',
                'body' => 'Body {{ inquiry_reference }}',
            ]);
        }

        return $template;
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'Template Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'template-limited-'.$user->id,
            'name' => 'Template limited user',
        ]);

        foreach ($keys as $key) {
            $definition = (array) config('permissions.'.$key, []);
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'website',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }
}
