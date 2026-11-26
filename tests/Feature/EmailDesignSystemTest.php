<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AuditLifecycleNotification;
use App\Notifications\CmsEmailTemplateTestMail;
use App\Notifications\InquiryReplyMail;
use App\Notifications\Phase5\Phase5Mail;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\EmailMessagePresenter;
use App\Services\EmailTemplatePreviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Part\DataPart;
use Tests\TestCase;

final class EmailDesignSystemTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_LOGO_HASH = '5834fb72446a75cd56ed6a51a06ae1dafcaa56559c62e6906603978eb27ecb9e';

    private const LAYOUT_MARKER = 'data-pcdr-email="layout-v1"';

    private const CID_PLACEHOLDER = 'cid:pcdr-email-logo@pcdr.local';

    public function test_dedicated_logo_assets_are_small_opaque_pngs_derived_from_the_source(): void
    {
        $embedded = resource_path('mail/email-logo.png');
        $public = public_path('assets/email/email-logo.png');

        foreach ([$embedded, $public] as $path) {
            $this->assertFileExists($path);
            $this->assertLessThan(40960, filesize($path));

            $info = getimagesize($path);
            $this->assertIsArray($info);
            $this->assertSame(240, $info[0]);
            $this->assertSame(240, $info[1]);
            $this->assertSame(IMAGETYPE_PNG, $info[2]);

            $bytes = (string) file_get_contents($path);
            $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8));
            $this->assertSame(3, ord($bytes[25]), 'the logo must use an opaque indexed palette');
            $this->assertStringNotContainsString('tRNS', $bytes, 'the logo must not have transparency');
        }

        $this->assertSame(hash_file('sha256', $embedded), hash_file('sha256', $public));
        $this->assertNotSame(self::SOURCE_LOGO_HASH, hash_file('sha256', $embedded));
        $this->assertSame(
            self::SOURCE_LOGO_HASH,
            hash_file('sha256', public_path('assets/pcdr-homepage/logo/pcdr-logo.png')),
            'the original circular logo must remain untouched',
        );
    }

    public function test_real_documents_use_the_inline_cid_placeholder_by_default(): void
    {
        config()->set('app.url', 'http://localhost:8000');
        config()->set('email.brand.logo_url', null);
        config()->set('email.brand.logo_mode', 'cid');

        $html = app(EmailMessagePresenter::class)->document('Hello there', 'en', [
            'title' => 'Institutional update',
            'reference' => 'PCDR-REF-1',
        ]);

        $this->assertStringContainsString('<html lang="en" dir="ltr">', $html);
        $this->assertStringContainsString(self::LAYOUT_MARKER, $html);
        $this->assertStringContainsString('data-pcdr-email-logo="true"', $html);
        $this->assertStringContainsString('src="'.self::CID_PLACEHOLDER.'"', $html);
        $this->assertStringContainsString('width="120" height="120"', $html);
        $this->assertStringContainsString('#25334B', $html);
        $this->assertStringContainsString("font-family:'IBM Plex Sans Arabic',Tahoma,Arial,sans-serif", $html);
        $this->assertStringContainsString('PCDR-REF-1', $html);
        $this->assertStringContainsString('This message was sent through the institutional email system.', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('.svg', $html);
        $this->assertStringNotContainsString('.webp', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('http://', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_arabic_and_english_documents_use_correct_direction_alignment_and_alt_text(): void
    {
        $presenter = app(EmailMessagePresenter::class);

        $arabic = $presenter->document('نص الرسالة', 'ar', [
            'title' => 'موضوع الرسالة',
            'reference' => 'REF-1',
        ]);
        $english = $presenter->document('Message body', 'en', [
            'title' => 'Subject',
            'reference' => 'REF-1',
        ]);

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $arabic);
        $this->assertStringContainsString('text-align:right', $arabic);
        $this->assertStringContainsString('border-right:3px solid', $arabic);
        $this->assertStringContainsString('شعار', $arabic);
        $this->assertStringContainsString('مراسلات مؤسسية', $arabic);

        $this->assertStringContainsString('<html lang="en" dir="ltr">', $english);
        $this->assertStringContainsString('text-align:left', $english);
        $this->assertStringNotContainsString('border-right:3px solid', $english);
        $this->assertStringContainsString(' logo"', $english);
        $this->assertStringContainsString('Institutional correspondence', $english);
    }

    public function test_url_mode_requires_public_https_and_falls_back_to_cid(): void
    {
        config()->set('email.brand.logo_mode', 'url');
        config()->set('email.brand.logo_url', null);
        config()->set('email.brand.asset_url', 'https://cdn.example.org');

        $html = app(EmailMessagePresenter::class)->document('Hello', 'en', ['title' => 'Subject']);

        $this->assertStringContainsString('src="https://cdn.example.org/assets/email/email-logo.png"', $html);
        $this->assertStringNotContainsString(self::CID_PLACEHOLDER, $html);
        $this->assertStringNotContainsString('data:image', $html);

        Log::spy();
        config()->set('email.brand.asset_url', null);
        config()->set('app.url', 'http://localhost:8000');

        $fallback = app(EmailMessagePresenter::class)->document('Hello', 'en', ['title' => 'Subject']);

        $this->assertStringContainsString('src="'.self::CID_PLACEHOLDER.'"', $fallback);
        Log::shouldHaveReceived('warning')->once();

        foreach ([
            'http://cdn.example.org',
            'https://pcdr.test',
            'https://pcdr.local',
            'https://127.0.0.1',
            'https://10.0.0.5',
        ] as $unsafeBase) {
            config()->set('app.url', $unsafeBase);

            $html = app(EmailMessagePresenter::class)->document('Hello', 'en', ['title' => 'Subject']);

            $this->assertStringContainsString('src="'.self::CID_PLACEHOLDER.'"', $html, $unsafeBase);
            $this->assertStringNotContainsString($unsafeBase, $html, $unsafeBase);
        }
    }

    public function test_every_default_template_renders_preview_with_inline_logo_in_both_locales(): void
    {
        $previews = app(EmailTemplatePreviewService::class);
        $keys = array_keys((array) config('email.default_templates', []));

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            foreach (['ar', 'en'] as $locale) {
                $preview = $previews->build(null, (string) $key, $locale);
                $html = (string) $preview['html'];

                $this->assertStringContainsString(self::LAYOUT_MARKER, $html, (string) $key.'/'.$locale);
                $this->assertStringContainsString('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">', $html);
                $this->assertStringContainsString('data-email-preview="true"', $html);
                $this->assertStringContainsString('data:image/png;base64,', $html);
                $this->assertStringContainsString('data-pcdr-email-logo="true"', $html);
                $this->assertStringContainsString((string) $preview['subject'], $html);
                $this->assertStringNotContainsString(self::CID_PLACEHOLDER, $html);
                $this->assertStringNotContainsString('{{', $html);
                $this->assertStringNotContainsString('localhost', $html);
                $this->assertStringNotContainsString('.svg', $html);
                $this->assertStringNotContainsString('.webp', $html);
                $this->assertSame($locale, $preview['locale']);
                $this->assertSame($locale === 'ar' ? 'rtl' : 'ltr', $preview['direction']);
            }
        }
    }

    public function test_preview_can_simulate_blocked_images_without_hiding_the_organization_name(): void
    {
        $preview = app(EmailTemplatePreviewService::class)->build(
            null,
            'inquiry_received',
            'ar',
            null,
            null,
            true,
        );
        $html = (string) $preview['html'];

        $this->assertStringContainsString(self::LAYOUT_MARKER, $html);
        $this->assertStringContainsString('data-email-logo-fallback="true"', $html);
        $this->assertStringContainsString('شعار', $html);
        $this->assertStringContainsString('font-size:13px', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('width="120" height="120"', $html);
    }

    public function test_mailable_notification_and_queued_mail_share_the_inline_logo_and_plain_text(): void
    {
        config()->set('email.brand.logo_mode', 'cid');

        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent): void {
            $sent[] = $event->message;
        });

        Mail::to('recipient@example.org')->send(new InquiryReplyMail(
            'Reply subject',
            'Hello from the mailable',
            'en',
            'REF-42',
        ));

        Notification::route('mail', 'recipient@example.org')->notify(new AuditLifecycleNotification(
            'Audit subject',
            'Hello from the notification',
            'info',
        ));

        Mail::to('recipient@example.org')->sendNow(new QueuedLogoTestMail);

        $this->assertCount(3, $sent);

        foreach ($sent as $message) {
            $this->assertInstanceOf(SymfonyEmail::class, $message);

            $html = (string) $message->getHtmlBody();
            $text = (string) $message->getTextBody();

            $this->assertStringContainsString(self::LAYOUT_MARKER, $html);
            $this->assertStringContainsString('cid:', $html);
            $this->assertStringNotContainsString(self::CID_PLACEHOLDER, $html);
            $this->assertStringNotContainsString('data:image', $html);
            $this->assertStringNotContainsString('.svg', $html);
            $this->assertStringNotContainsString('.webp', $html);
            $this->assertNotSame('', trim($text));
            $this->assertStringNotContainsString('cid:', $text);

            $attachments = $message->getAttachments();
            $this->assertCount(1, $attachments);
            $logo = $attachments[0];
            $this->assertInstanceOf(DataPart::class, $logo);
            $this->assertSame('email-logo.png', $logo->getFilename());
            $this->assertSame('image/png', $logo->getMediaType().'/'.$logo->getMediaSubtype());
            $this->assertNotSame('', $logo->getContentId());
            $this->assertStringContainsString('cid:'.$logo->getContentId(), $html);
        }

        $this->assertStringContainsString('REF-42', (string) $sent[0]->getHtmlBody());
        $this->assertStringContainsString('Hello from the notification', (string) $sent[1]->getTextBody());
        $this->assertStringContainsString('Queued body', (string) $sent[2]->getHtmlBody());
    }

    public function test_cms_test_mail_uses_the_real_delivery_path_with_cid_logo(): void
    {
        config()->set('email.brand.logo_mode', 'cid');

        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent): void {
            $sent[] = $event->message;
        });

        Mail::to('recipient@example.org')->send(new CmsEmailTemplateTestMail(
            'Template test subject',
            'Test body',
            'ar',
        ));

        $this->assertCount(1, $sent);
        $message = $sent[0];
        $html = (string) $message->getHtmlBody();

        $this->assertInstanceOf(SymfonyEmail::class, $message);
        $this->assertStringContainsString('Template test subject', $html);
        $this->assertStringContainsString('Test body', $html);
        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringNotContainsString(self::CID_PLACEHOLDER, $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('data-email-preview="true"', $html);
        $this->assertCount(1, $message->getAttachments());
    }

    public function test_notification_emails_are_wrapped_in_the_shared_layout(): void
    {
        $presenter = app(EmailMessagePresenter::class);

        $audit = (new AuditLifecycleNotification('Audit title', 'Audit message', 'info', ['Actor' => 'Admin']))
            ->toMail((object) ['locale' => 'ar'])
            ->render();

        $phase5 = (string) (new Phase5Mail('phase5.notification.case_created', 'preview', 'en'))->render();

        $user = User::factory()->create(['locale' => 'en']);
        $verification = (string) (new VerifyEmailNotification)->toMail($user)->render();
        $reset = (string) (new ResetPasswordNotification('reset-token'))->toMail($user)->render();

        foreach ([$audit, $phase5, $verification, $reset] as $html) {
            $this->assertStringContainsString(self::LAYOUT_MARKER, $html);
            $this->assertStringContainsString('src="'.self::CID_PLACEHOLDER.'"', $html);
            $this->assertStringNotContainsString('data:image', $html);
        }

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $audit);
        $this->assertStringContainsString('Audit title', $audit);
        $this->assertStringContainsString('Audit message', $audit);
        $this->assertStringContainsString('Case recorded with a safe reference number.', $phase5);
        $this->assertStringContainsString('Verify email address', $verification);
        $this->assertStringContainsString('Reset password', $reset);
    }

    public function test_untrusted_html_is_sanitized_inside_the_layout(): void
    {
        $html = app(EmailMessagePresenter::class)->document(
            '<p onclick="alert(1)">Hello</p><script>alert("xss")</script>'
            .'<a href="javascript:alert(1)">unsafe</a><img src="https://cdn.example.org/x.png" onerror="alert(1)">',
            'en',
            ['title' => 'Subject'],
        );

        $this->assertStringContainsString(self::LAYOUT_MARKER, $html);
        $this->assertStringContainsString('<p>Hello</p>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}

final class QueuedLogoTestMail extends Mailable implements ShouldQueue
{
    public function build(): self
    {
        $presenter = app(EmailMessagePresenter::class);

        return $this
            ->subject('Queued test')
            ->view([
                'html' => new HtmlString($presenter->document(
                    'Queued body',
                    'en',
                    ['title' => 'Queued test'],
                )),
                'text' => new HtmlString('Queued body'),
            ]);
    }
}
