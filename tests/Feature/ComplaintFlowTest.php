<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Cms\ComplaintController as CmsComplaintController;
use App\Http\Controllers\Cms\InquiryController;
use App\Http\Controllers\Public\ComplaintController as PublicComplaintController;
use App\Models\Complaint;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ComplaintFlowTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALE = 'en';

    private const BOOTSTRAP_EMAIL = 'complaint-editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', '12345678901234567890123456789012');
        config()->set('cms.bootstrap.email', self::BOOTSTRAP_EMAIL);
        config()->set('cms.local_auth_enabled', true);
        config()->set('complaints.notifications.status_email_enabled', false);

        Notification::fake();
        $this->withoutVite();
        $this->registerFallbackRoutes();
    }

    public function test_public_page_exposes_canonical_config(): void
    {
        $this->get(route('public.complaints', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/Complaints')
                ->where('complaintConfig.attachmentLimits.maxFiles', 3)
                ->where('complaintConfig.attachmentLimits.maxFileSizeKb', 5120)
                ->where('complaintConfig.categories', fn ($categories): bool => collect($categories)
                    ->contains(fn (array $category): bool => $category['value'] === 'data_privacy'))
                ->where('complaintConfig.storeUrl', route('public.complaints.store', ['locale' => self::LOCALE]))
                ->where('submittedReference', null));
    }

    public function test_anonymous_submission_strips_identity_and_records_reference(): void
    {
        $response = $this->post(route('public.complaints.store', ['locale' => self::LOCALE]), $this->payload([
            'name' => 'Hidden Person',
            'contact_method' => 'email',
            'email' => 'hidden@example.test',
            'phone' => '+970500000000',
        ]));

        $response->assertRedirect()->assertSessionHas('flash.reference');
        $reference = session('flash.reference');

        $complaint = Complaint::query()->where('reference', $reference)->firstOrFail();

        $this->assertTrue($complaint->isAnonymous());
        $this->assertNull($complaint->complainant_name);
        $this->assertNull($complaint->email);
        $this->assertNull($complaint->phone);
        $this->assertNull($complaint->preferred_contact_method);
        $this->assertSame('en', $complaint->locale);
        $this->assertSame('new', $complaint->status->value);
        $this->assertNotNull($complaint->ip_hash);
        $this->assertSame(1, $complaint->statusHistories()->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'complaints.submitted',
            'subject_type' => 'complaint',
            'subject_id' => (string) $complaint->id,
        ]);
    }

    public function test_identified_submission_requires_contact_details_and_rejects_honeypot(): void
    {
        $identified = $this->payload([
            'is_anonymous' => false,
            'name' => 'Concerned Person',
            'contact_method' => 'email',
            'email' => 'concerned@example.test',
        ]);

        $this->post(route('public.complaints.store', ['locale' => self::LOCALE]), array_merge($identified, [
            'honeypot' => 'filled-by-bot',
        ]))->assertSessionHasErrors('honeypot');

        $this->assertSame(0, Complaint::query()->count());

        $this->post(route('public.complaints.store', ['locale' => self::LOCALE]), $identified)
            ->assertRedirect()
            ->assertSessionHas('flash.reference');

        $this->assertDatabaseHas('complaints', [
            'complainant_name' => 'Concerned Person',
            'preferred_contact_method' => 'email',
            'email' => 'concerned@example.test',
            'is_anonymous' => false,
        ]);
    }

    public function test_public_submission_validates_body_and_attachment_limits(): void
    {
        $this->post(route('public.complaints.store', ['locale' => self::LOCALE]), $this->payload([
            'body' => 'Too short',
        ]))->assertSessionHasErrors('body');

        $files = [
            UploadedFile::fake()->create('one.pdf', 1, 'application/pdf'),
            UploadedFile::fake()->create('two.pdf', 1, 'application/pdf'),
            UploadedFile::fake()->create('three.pdf', 1, 'application/pdf'),
            UploadedFile::fake()->create('four.pdf', 1, 'application/pdf'),
        ];

        $this->post(route('public.complaints.store', ['locale' => self::LOCALE]), $this->payload([
            'attachments' => $files,
        ]))->assertSessionHasErrors('attachments');

        $this->assertSame(0, Complaint::query()->count());
    }

    public function test_complaint_index_is_hidden_without_permission_and_scoped_to_assignment(): void
    {
        $viewer = $this->userWith([
            'inquiries.view', 'inquiries.view_assigned', 'complaints.view_assigned',
        ]);
        $assigned = Complaint::factory()->create([
            'assigned_to' => $viewer->id,
            'email' => 'assigned-complainant@example.test',
        ]);
        Complaint::factory()->create(['assigned_to' => null]);

        $this->actingAs($viewer)->get(route('cms.inquiries.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Inquiries/Index')
                ->where('inquiries.complaints', fn ($rows): bool => count($rows) === 1
                    && $rows[0]['id'] === $assigned->id)
                ->where('inquiries.complaints.0.email', null)
                ->where('pagination.complaints.total', 1)
                ->where('can.complaintViewAll', false)
                ->where('can.complaintViewAssigned', true));

        $outsider = $this->userWith(['inquiries.view']);

        $this->actingAs($outsider)->get(route('cms.inquiries.index', ['locale' => self::LOCALE]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inquiries.complaints', [])
                ->where('pagination.complaints.total', 0)
                ->where('can.complaintView', false));
    }

    public function test_complaint_detail_gates_contact_information_separately(): void
    {
        $complaint = Complaint::factory()->create([
            'category' => 'service_delivery',
            'email' => 'visible-complainant@example.test',
            'complainant_name' => 'Visible Complainant',
        ]);
        $url = $this->complaintDetailUrl($complaint);

        $this->actingAs($this->bootstrapUser())->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cms/Inquiries/Show')
                ->where('type', 'complaint')
                ->where('inquiry.category', 'service_delivery')
                ->where('inquiry.priority', 'normal')
                ->where('contact.email', 'visible-complainant@example.test')
                ->where('can.complaintUpdate', true)
                ->where('urls.status', route('cms.complaints.status', [
                    'locale' => self::LOCALE,
                    'complaint' => $complaint->id,
                ])));

        $limited = $this->userWith(['inquiries.view', 'complaints.view_all']);

        $this->actingAs($limited)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('inquiry.reference', $complaint->reference)
                ->where('contact', null)
                ->where('can.complaintViewContact', false));
    }

    public function test_cms_workflow_validates_transitions_and_records_audit_history(): void
    {
        $editor = $this->bootstrapUser();
        $complaint = Complaint::factory()->create(['status' => 'new', 'assigned_to' => null]);

        $this->actingAs($editor)->put($this->actionUrl('status', $complaint), ['status' => 'resolved'])
            ->assertSessionHasErrors('status');

        $this->assertSame('new', $complaint->fresh()->status->value);

        $this->put($this->actionUrl('status', $complaint), ['status' => 'under_review'])->assertRedirect();
        $this->put($this->actionUrl('priority', $complaint), ['priority' => 'high'])->assertRedirect();
        $this->put($this->actionUrl('assign', $complaint), ['assigned_to' => $editor->id])->assertRedirect();
        $this->post($this->actionUrl('note', $complaint), ['body' => 'Reviewed supporting documents.'])->assertRedirect();
        $this->post($this->actionUrl('resolve', $complaint), ['resolution_details' => 'Response sent.'])->assertRedirect();

        $complaint->refresh();

        $this->assertSame('resolved', $complaint->status->value);
        $this->assertSame('high', $complaint->priority->value);
        $this->assertSame($editor->id, $complaint->assigned_to);
        $this->assertSame('Response sent.', $complaint->resolution_details);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame(2, $complaint->statusHistories()->count());
        $this->assertSame(1, $complaint->notes()->count());

        foreach ([
            'complaints.status_changed', 'complaints.priority_updated', 'complaints.assigned',
            'complaints.note_added', 'complaints.resolved',
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
                'subject_type' => 'complaint',
                'subject_id' => (string) $complaint->id,
            ]);
        }
    }

    public function test_scoped_staff_cannot_mutate_unassigned_or_unauthorized_complaints(): void
    {
        $staff = $this->userWith([
            'inquiries.view', 'complaints.view_assigned', 'complaints.update',
            'complaints.assign', 'complaints.add_internal_note', 'complaints.resolve',
        ]);
        $assigned = Complaint::factory()->create(['assigned_to' => $staff->id]);
        $unassigned = Complaint::factory()->create(['assigned_to' => null]);

        $this->actingAs($staff)->put($this->actionUrl('status', $unassigned), ['status' => 'under_review'])
            ->assertForbidden();
        $this->assertSame('new', $unassigned->fresh()->status->value);

        $this->put($this->actionUrl('status', $assigned), ['status' => 'under_review'])->assertRedirect();
        $this->assertSame('under_review', $assigned->fresh()->status->value);

        $viewer = $this->userWith(['inquiries.view', 'complaints.view_all']);

        $this->actingAs($viewer)->put($this->actionUrl('status', $assigned), ['status' => 'in_progress'])
            ->assertForbidden();
        $this->assertSame('under_review', $assigned->fresh()->status->value);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'service_delivery',
            'subject' => 'Concern about service delivery',
            'body' => 'This is a sufficiently detailed description of the concern.',
            'related_project' => '',
            'location' => 'Gaza',
            'is_anonymous' => true,
            'name' => '',
            'contact_method' => '',
            'email' => '',
            'phone' => '',
            'locale' => self::LOCALE,
            'attachments' => [],
            'honeypot' => '',
        ], $overrides);
    }

    private function bootstrapUser(): User
    {
        return User::factory()->create(['email' => self::BOOTSTRAP_EMAIL]);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'key' => 'complaint-test-'.$user->id,
            'name' => 'Complaint test user',
        ]);

        foreach ($keys as $key) {
            $permission = Permission::query()->firstOrCreate(['key' => $key], [
                'name' => $key,
                'module' => 'website',
                'is_protected' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->getKey()]);

        return $user;
    }

    private function actionUrl(string $action, Complaint $complaint): string
    {
        $name = $action === 'note' ? 'cms.complaints.notes' : 'cms.complaints.'.$action;

        return route($name, [
            'locale' => self::LOCALE,
            'complaint' => $complaint->id,
        ]);
    }

    private function complaintDetailUrl(Complaint $complaint): string
    {
        return Route::has('cms.inquiries.complaint.show')
            ? route('cms.inquiries.complaint.show', [
                'locale' => self::LOCALE,
                'inquiry' => $complaint->id,
            ])
            : route('cms.inquiries.show', [
                'locale' => self::LOCALE,
                'type' => 'complaint',
                'inquiry' => $complaint->id,
            ]);
    }

    private function registerFallbackRoutes(): void
    {
        Route::middleware('web')->group(function (): void {
            if (! Route::has('public.complaints')) {
                Route::get('/{locale}/complaints', [PublicComplaintController::class, 'index'])
                    ->where('locale', 'ar|en')
                    ->name('public.complaints');
            }

            if (! Route::has('public.complaints.store')) {
                Route::post('/{locale}/complaints', [PublicComplaintController::class, 'store'])
                    ->where('locale', 'ar|en')
                    ->name('public.complaints.store');
            }

            $actions = [
                'status' => ['put', 'status'],
                'priority' => ['put', 'priority'],
                'assign' => ['put', 'assign'],
                'resolve' => ['post', 'resolve'],
            ];

            foreach ($actions as $action => [$method, $controllerAction]) {
                if (Route::has('cms.complaints.'.$action)) {
                    continue;
                }

                Route::$method('/{locale}/cms/complaints/{complaint}/'.$action, [CmsComplaintController::class, $controllerAction])
                    ->where('locale', 'ar|en')
                    ->whereNumber('complaint')
                    ->name('cms.complaints.'.$action);
            }

            if (! Route::has('cms.complaints.notes')) {
                Route::post('/{locale}/cms/complaints/{complaint}/notes', [CmsComplaintController::class, 'note'])
                    ->where('locale', 'ar|en')
                    ->whereNumber('complaint')
                    ->name('cms.complaints.notes');
            }

            if (! Route::has('cms.inquiries.complaint.show')) {
                Route::get('/{locale}/cms/inquiries/complaint/{inquiry}', function (string $locale, int $inquiry) {
                    return app(InquiryController::class)->show($locale, 'complaint', $inquiry);
                })
                    ->where('locale', 'ar|en')
                    ->whereNumber('inquiry')
                    ->name('cms.inquiries.complaint.show');
            }
        });

        Route::getRoutes()->refreshNameLookups();
    }
}
