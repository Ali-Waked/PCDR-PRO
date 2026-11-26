<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\EmailDeliveryLog;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateTranslation;
use App\Models\InquiryNote;
use App\Models\InquiryReply;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InquiryReplyMail;
use App\Services\AccessManagementService;
use App\Services\InquiryMailService;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WebsiteCmsInquiryTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'ar';

    private const BOOTSTRAP_EMAIL = 'inquiry-editor@example.test';

    private const INTERNAL_RECIPIENT = 'inquiries@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        config()->set('email.internal_notification_recipient', self::INTERNAL_RECIPIENT);

        Mail::fake();
        app(SiteSettings::class)->flush();
        $this->withoutVite();
    }

    public function test_inquiry_index_requires_view_permission_and_scopes_assigned_records(): void
    {
        $unauthorized = $this->userWith(['pillars.view']);
        $this->actingAs($unauthorized)->get(route('cms.inquiries.index', ['locale' => self::LOCALE]))->assertForbidden();

        $viewer = $this->userWith(['inquiries.view', 'inquiries.view_assigned']);
        $assigned = $this->createContact(['assigned_to' => $viewer->id]);
        $this->createContact(['assigned_to' => null]);

        $this->actingAs($viewer)->get(route('cms.inquiries.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Inquiries/Index')
                ->where('inquiries.contact', fn ($rows): bool => count($rows) === 1
                    && $rows[0]['id'] === $assigned->id)
                ->where('pagination.contact.total', 1)
                ->where('pagination.contact.pageName', 'contactPage')
                ->where('counts.total', 1)
                ->where('counts.new', 1)
                ->where('counts.unassigned', 0)
                ->where('inquiries.contact.0.senderName', 'Contact Sender')
                ->where('inquiries.contact.0.subject', 'Website inquiry')
                ->where('can.viewAll', false)
                ->where('can.update', false));
    }

    public function test_inquiry_index_counts_normalize_status_aliases_and_ignore_list_filters(): void
    {
        $editor = $this->bootstrapUser();
        $other = User::factory()->create();

        $this->createContact(['status' => 'unread', 'assigned_to' => $other->id]);
        $this->createContact(['status' => 'open']);
        $this->createContact(['status' => 'answered']);
        $this->createContact(['status' => 'rejected']);
        $this->createContact(['status' => 'new']);

        $this->actingAs($editor)->get(route('cms.inquiries.index', [
            'locale' => self::LOCALE,
            'status' => 'new',
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('counts.total', 5)
            ->where('counts.new', 2)
            ->where('counts.in_progress', 1)
            ->where('counts.resolved', 1)
            ->where('counts.closed', 1)
            ->where('counts.unassigned', 4)
            ->where('pagination.contact.total', 2)
            ->where('canonicalStatuses', ['new', 'in_progress', 'resolved', 'closed'])
            ->where('filters.status', 'new'));
    }

    public function test_scoped_support_user_cannot_mutate_an_unassigned_inquiry_by_direct_id(): void
    {
        $support = $this->userWith([
            'inquiries.view', 'inquiries.view_assigned',
            'inquiries.update', 'inquiries.add_internal_note', 'inquiries.reply',
        ]);
        $unassigned = $this->createContact(['assigned_to' => null]);
        $assigned = $this->createContact(['assigned_to' => $support->id]);

        $route = fn (ContactMessage $inquiry, string $action): string => route('cms.inquiries.'.$action, [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]);

        $this->actingAs($support)->put($route($unassigned, 'status'), ['status' => 'open'])->assertForbidden();
        $this->post($route($unassigned, 'note'), ['body' => 'Unauthorized note'])->assertForbidden();
        $this->post($route($unassigned, 'reply'), [
            'subject' => 'Unauthorized reply',
            'message' => 'Unauthorized answer',
            'locale' => self::LOCALE,
        ])->assertForbidden();

        $this->assertSame('new', $unassigned->fresh()->status);
        $this->assertSame(0, InquiryNote::query()->where('inquiry_id', $unassigned->id)->count());
        $this->assertSame(0, InquiryReply::query()->where('inquiry_id', $unassigned->id)->count());

        $this->put($route($assigned, 'status'), ['status' => 'open'])->assertRedirect();

        $this->assertSame('in_progress', $assigned->fresh()->status);
    }

    public function test_inquiry_detail_updates_status_assignment_and_internal_notes(): void
    {
        $editor = $this->bootstrapUser();
        $inquiry = $this->createContact();

        $this->actingAs($editor)->get(route('cms.inquiries.show', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cms/Inquiries/Show')
            ->where('inquiry.reference', $inquiry->reference)
            ->where('can.update', true)
            ->where('can.assign', true)
            ->where('can.note', true));

        $this->put(route('cms.inquiries.status', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]), ['status' => 'open'])->assertRedirect();

        $this->assertSame('in_progress', $inquiry->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inquiries.status_updated',
            'subject_type' => 'contact',
            'subject_id' => (string) $inquiry->id,
        ]);

        $this->put(route('cms.inquiries.assign', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]), ['assigned_to' => $editor->id])->assertRedirect();

        $this->assertSame($editor->id, $inquiry->fresh()->assigned_to);

        $this->post(route('cms.inquiries.note', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]), ['body' => 'Internal handling note'])->assertRedirect();

        $this->assertSame(1, InquiryNote::query()->where('inquiry_id', $inquiry->id)->count());
        $this->assertDatabaseHas('inquiry_notes', ['body' => 'Internal handling note']);
    }

    public function test_reply_renders_template_records_delivery_and_is_idempotent(): void
    {
        $editor = $this->bootstrapUser();
        $inquiry = $this->createContact(['email' => 'reply-target@example.test']);

        $template = EmailTemplate::query()->create(['key' => 'custom_reply', 'is_active' => true]);
        EmailTemplateTranslation::query()->create([
            'email_template_id' => $template->id,
            'locale' => self::LOCALE,
            'name' => 'Custom reply',
            'subject' => 'Answer {{ inquiry_reference }}',
            'body' => 'Hello {{ sender_name }}: {{ reply_message }} {{ unknown_variable }}',
        ]);

        $this->actingAs($editor)->post(route('cms.inquiries.reply', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]), [
            'template_id' => $template->id,
            'subject' => 'Ignored subject',
            'message' => 'Approved answer',
            'locale' => self::LOCALE,
        ])->assertRedirect()->assertSessionHas('flash.success');

        $reply = InquiryReply::query()->firstOrFail();

        $this->assertStringContainsString((string) $inquiry->reference, $reply->subject);
        $this->assertStringContainsString('Approved answer', $reply->body);
        $this->assertStringContainsString('Contact Sender', $reply->body);
        $this->assertStringNotContainsString('{{ unknown_variable }}', $reply->body);
        $this->assertSame('sent', $reply->status);

        $log = EmailDeliveryLog::query()->firstOrFail();

        $this->assertSame('inquiry_reply', $log->purpose);
        $this->assertSame('sent', $log->status);
        $this->assertSame($inquiry->reference, $log->inquiry_reference);
        $this->assertSame(hash('sha256', 'reply-target@example.test'), $log->recipient_hash);

        Mail::assertSent(InquiryReplyMail::class, function (InquiryReplyMail $mail): bool {
            return $mail->hasTo('reply-target@example.test')
                && str_contains($mail->renderedBody, 'Approved answer');
        });

        app(InquiryMailService::class)->sendReply($reply);

        $this->assertSame(1, EmailDeliveryLog::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'inquiries.replied',
            'subject_type' => 'contact',
            'subject_id' => (string) $inquiry->id,
        ]);
    }

    public function test_reply_delivery_failure_is_reported_honestly(): void
    {
        $editor = $this->bootstrapUser();
        $inquiry = $this->createContact(['email' => 'failure-target@example.test']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP unavailable'));
        Mail::shouldReceive('failures')->andReturn([]);

        $this->actingAs($editor)->post(route('cms.inquiries.reply', [
            'locale' => self::LOCALE,
            'type' => 'contact',
            'inquiry' => $inquiry->id,
        ]), [
            'subject' => 'Reply',
            'message' => 'Approved answer',
            'locale' => self::LOCALE,
        ])->assertRedirect()->assertSessionHas('flash.error');

        $reply = InquiryReply::query()->firstOrFail();
        $log = EmailDeliveryLog::query()->firstOrFail();

        $this->assertSame('failed', $reply->status);
        $this->assertStringContainsString('SMTP unavailable', (string) $reply->error);
        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->failed_at);
    }

    /** @param array<string, mixed> $overrides */
    private function createContact(array $overrides = []): ContactMessage
    {
        return ContactMessage::query()->create(array_merge([
            'reference' => (string) Str::ulid(),
            'category' => 'general',
            'name' => 'Contact Sender',
            'email' => 'contact-sender@example.test',
            'subject' => 'Website inquiry',
            'message' => 'A sufficiently long message for testing.',
            'locale' => self::LOCALE,
            'consent' => true,
            'status' => 'new',
        ], $overrides));
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create([
            'name' => 'Inquiry Editor',
            'email' => self::BOOTSTRAP_EMAIL,
            'password' => Hash::make('password'),
        ]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'inquiry-limited-'.$user->id,
            'name' => 'Inquiry limited user',
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
