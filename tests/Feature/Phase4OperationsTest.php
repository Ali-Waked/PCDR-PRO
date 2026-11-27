<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\OperationalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class Phase4OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_phase4_schema_exists_without_prohibited_privacy_or_approval_tables(): void
    {
        foreach ([
            'locations', 'interventions', 'activities', 'distribution_plans',
            'distribution_rounds', 'distribution_records', 'warehouses',
            'inventory_items', 'inventory_balances', 'inventory_movements',
            'volunteers', 'volunteer_teams', 'volunteer_assignments',
            'committees', 'committee_meetings', 'community_feedback',
            'partners', 'partner_agreements', 'partner_contacts',
            'grant_opportunities', 'grant_proposals', 'grant_awards',
            'results_frameworks', 'indicators', 'indicator_measurements',
            'evaluations', 'corrective_actions', 'assessments',
            'data_collection_instruments', 'operational_reports',
            'operational_translations', 'domain_transitions', 'document_links',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing operational table [{$table}].");
        }

        // The public complaints box is an intentional standalone channel, not a
        // beneficiary or approval register.
        $this->assertTrue(Schema::hasTable('complaints'), 'Missing complaints table.');

        foreach ([
            'beneficiaries', 'households',
            'institutional_approvals', 'delegations', 'officeholders',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Prohibited table [{$table}] exists.");
        }
    }

    public function test_privacy_and_authority_gates_are_closed_and_no_approval_permission_exists(): void
    {
        foreach ([
            'institutional_approvals_enabled', 'beneficiary_records_enabled',
            'sensitive_disaggregation_enabled', 'inventory_valuation_enabled',
        ] as $gate) {
            $this->assertFalse(config("operations.gates.{$gate}"));
        }

        $keys = array_keys(config('permissions', []));
        $this->assertNotContains('grant_proposals.approve', $keys);
        $this->assertNotContains('operations.approve', $keys);

        foreach ($keys as $key) {
            $this->assertStringNotContainsString('.approve', $key);
        }
    }

    public function test_generic_resource_routes_require_authentication_and_permission(): void
    {
        $this->get('/ar/operations/locations')->assertRedirect('/ar/login');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/ar/operations/locations')->assertForbidden();

        $viewer = $this->userWith(['locations.view']);
        $this->actingAs($viewer)->get('/ar/operations/locations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Operations/ResourceIndex')
                ->where('resource', 'locations'));
    }

    public function test_authorized_user_can_create_update_and_soft_delete_a_translated_record(): void
    {
        $user = $this->userWith([
            'locations.view', 'locations.create', 'locations.update',
        ]);

        $this->actingAs($user)->post('/ar/operations/locations', [
            'code' => 'LOC-001',
            'location_type' => 'district',
            'status' => 'active',
            'is_platform_owner' => true,
            'unknown_column' => 'ignored',
            'translations' => [
                'ar' => ['name' => 'حي القدس', 'summary' => 'ملخص <b>آمن</b>'],
                'en' => ['name' => 'Jerusalem district'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('locations', ['code' => 'LOC-001', 'status' => 'active']);
        $this->assertDatabaseMissing('locations', ['code' => 'LOC-001', 'unknown_column' => 'ignored']);
        $this->assertDatabaseHas('operational_translations', [
            'resource' => 'locations',
            'locale' => 'ar',
            'name' => 'حي القدس',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'operations.record_created',
            'subject_type' => 'locations',
        ]);

        $recordId = (int) DB::table('locations')->where('code', 'LOC-001')->value('id');

        $this->actingAs($user)->put("/ar/operations/locations/{$recordId}", [
            'code' => 'LOC-001',
            'location_type' => 'governorate',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertSame('governorate', DB::table('locations')->where('id', $recordId)->value('location_type'));

        $this->actingAs($user)->delete("/ar/operations/locations/{$recordId}")->assertRedirect();
        $this->assertSoftDeleted('locations', ['id' => $recordId]);
    }

    public function test_posting_a_gated_approval_status_is_rejected(): void
    {
        $user = $this->userWith(['grants.view', 'grants.create']);

        $this->actingAs($user)->post('/ar/operations/grant_proposals', [
            'code' => 'PROP-GATED',
            'currency' => 'USD',
            'status' => 'approved_for_submission',
            'translations' => ['ar' => ['title' => 'مقترح محجوز']],
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('grant_proposals', ['code' => 'PROP-GATED']);
    }

    public function test_transitions_follow_the_declared_lifecycle_and_block_gated_states(): void
    {
        $user = $this->userWith([
            'interventions.view', 'interventions.create', 'interventions.transition',
            'grants.view', 'grants.create', 'grants.transition',
        ]);

        $interventionId = $this->createRecord('interventions', [
            'code' => 'INT-001', 'category' => 'relief', 'status' => 'draft',
        ]);

        $this->actingAs($user)->post("/ar/operations/interventions/{$interventionId}/status", [
            'to_status' => 'completed',
        ])->assertSessionHasErrors('to_status');
        $this->assertSame('draft', DB::table('interventions')->where('id', $interventionId)->value('status'));

        $this->actingAs($user)->post("/ar/operations/interventions/{$interventionId}/status", [
            'to_status' => 'active',
        ])->assertRedirect();
        $this->assertSame('active', DB::table('interventions')->where('id', $interventionId)->value('status'));

        $proposalId = $this->createRecord('grant_proposals', [
            'code' => 'PROP-001', 'currency' => 'USD', 'status' => 'internal_review',
        ]);

        $this->actingAs($user)->post("/ar/operations/grant_proposals/{$proposalId}/status", [
            'to_status' => 'pending_approval',
        ])->assertSessionHasErrors('to_status');
        $this->assertSame('internal_review', DB::table('grant_proposals')->where('id', $proposalId)->value('status'));
        $this->assertDatabaseMissing('domain_transitions', [
            'resource' => 'grant_proposals', 'owner_id' => $proposalId, 'to_status' => 'pending_approval',
        ]);
    }

    public function test_inventory_ledger_is_transactional_idempotent_and_append_only(): void
    {
        $user = $this->userWith([
            'inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.reverse',
        ]);
        $warehouseId = $this->createRecord('warehouses', [
            'code' => 'WH-001', 'status' => 'active',
        ]);
        $itemId = $this->createRecord('inventory_items', [
            'code' => 'ITEM-001', 'category' => 'food', 'unit' => 'kg', 'status' => 'active',
        ]);

        $payload = [
            'warehouse_id' => $warehouseId,
            'inventory_item_id' => $itemId,
            'movement_type' => 'receipt',
            'quantity' => 10,
            'idempotency_key' => 'receipt-001',
            'reason' => 'Opening stock',
        ];

        $this->actingAs($user)->post('/ar/operations/inventory/movements', $payload)->assertRedirect();
        $this->actingAs($user)->post('/ar/operations/inventory/movements', $payload)->assertRedirect();

        $this->assertSame(1, DB::table('inventory_movements')->where('idempotency_key', 'receipt-001')->count());
        $this->assertSame(10.0, (float) DB::table('inventory_balances')->where('warehouse_id', $warehouseId)->value('quantity'));

        $this->actingAs($user)->post('/ar/operations/inventory/movements', [
            'warehouse_id' => $warehouseId,
            'inventory_item_id' => $itemId,
            'movement_type' => 'issue',
            'quantity' => 100,
        ])->assertSessionHasErrors('quantity');
        $this->assertSame(10.0, (float) DB::table('inventory_balances')->where('warehouse_id', $warehouseId)->value('quantity'));

        $this->actingAs($user)->post('/ar/operations/inventory/movements', [
            'warehouse_id' => $warehouseId,
            'inventory_item_id' => $itemId,
            'movement_type' => 'issue',
            'quantity' => 4,
        ])->assertRedirect();
        $this->assertSame(6.0, (float) DB::table('inventory_balances')->where('warehouse_id', $warehouseId)->value('quantity'));

        $movementId = (int) DB::table('inventory_movements')->where('movement_type', 'issue')->value('id');
        $this->actingAs($user)->post("/ar/operations/inventory/movements/{$movementId}/reverse")->assertRedirect();
        $this->assertSame(1, DB::table('inventory_movements')->where('reversal_of', $movementId)->count());
        $this->assertSame(10.0, (float) DB::table('inventory_balances')->where('warehouse_id', $warehouseId)->value('quantity'));
    }

    public function test_restricted_document_download_requires_signature_and_permission(): void
    {
        $user = $this->userWith([
            'locations.view', 'documents.view', 'documents.download',
        ]);
        $recordId = $this->createRecord('locations', [
            'code' => 'LOC-DOC', 'location_type' => 'district', 'status' => 'active',
        ]);

        $media = Media::create([
            'uuid' => 'document-uuid-1',
            'disk' => 'private',
            'path' => 'media/private/document.pdf',
            'type' => MediaType::Document,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 16,
            'checksum' => hash('sha256', 'confidential'),
            'original_name' => 'confidential.pdf',
            'is_restricted' => true,
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('private')->put($media->path, 'confidential');

        $link = app(OperationalDocumentService::class)->attach(
            'locations', $recordId, $media, 'evidence', $user->id,
        );
        $url = app(OperationalDocumentService::class)->signedDownloadUrl($link, 'ar');

        $this->get($url)->assertRedirect();

        $this->actingAs($user)->get(strtok($url, '?'))->assertForbidden();

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $response = $this->actingAs($user)->get($url)->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_aggregate_reports_and_exports_never_leak_operational_identifiers(): void
    {
        $user = $this->userWith(['reports.view', 'reports.create', 'reports.export']);

        $this->createRecord('locations', [
            'code' => 'SECRET-LOCATION-CODE',
            'location_type' => 'district',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post('/ar/operations/reports/generate', [
            'category' => 'geographic',
            'format' => 'csv',
        ])->assertRedirect();

        $report = DB::table('operational_reports')->latest('id')->firstOrFail();
        $summary = json_decode((string) $report->summary, true, 512, JSON_THROW_ON_ERROR);
        $serialized = json_encode($summary, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('SECRET-LOCATION-CODE', $serialized);
        $this->assertSame('geographic', $summary['category']);
        $this->assertGreaterThan(0, $summary['total']);

        $response = $this->actingAs($user)
            ->get("/ar/operations/reports/{$report->id}/export")
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('table,status,count', $csv);
        $this->assertStringNotContainsString('SECRET-LOCATION-CODE', $csv);
    }

    public function test_operational_workflows_record_measurement_verification_and_submission_evidence(): void
    {
        $user = $this->userWith([
            'meal.view', 'meal.create', 'meal.verify',
            'grants.view', 'grants.create', 'grants.submit',
        ]);

        $indicatorId = $this->createRecord('indicators', [
            'code' => 'IND-001', 'indicator_type' => 'output', 'unit' => 'count',
            'calculation_rule' => 'count', 'status' => 'active',
        ]);
        $measurementId = $this->createRecord('indicator_measurements', [
            'indicator_id' => $indicatorId, 'reported_value' => 12,
            'status' => 'under_verification',
        ]);

        $this->actingAs($user)->post("/ar/operations/indicator-measurements/{$measurementId}/verify", [
            'verified_value' => 10,
            'verification_notes' => 'Reviewed against source totals.',
        ])->assertRedirect();

        $measurement = DB::table('indicator_measurements')->where('id', $measurementId)->firstOrFail();
        $this->assertSame('verified', $measurement->status);
        $this->assertSame(10.0, (float) $measurement->verified_value);

        $proposalId = $this->createRecord('grant_proposals', [
            'code' => 'PROP-SUBMIT', 'currency' => 'USD', 'status' => 'drafting',
        ]);
        $media = Media::create([
            'uuid' => 'evidence-uuid-1',
            'disk' => 'private',
            'path' => 'media/private/evidence.pdf',
            'type' => MediaType::Document,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 16,
            'checksum' => hash('sha256', 'evidence'),
            'original_name' => 'evidence.pdf',
            'is_restricted' => true,
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)->post("/ar/operations/grant-proposals/{$proposalId}/submit", [
            'evidence_media_id' => $media->id,
        ])->assertRedirect();

        $this->assertSame('submitted', DB::table('grant_proposals')->where('id', $proposalId)->value('status'));
        $this->assertDatabaseHas('domain_transitions', [
            'resource' => 'grant_proposals', 'owner_id' => $proposalId, 'to_status' => 'submitted',
        ]);
    }

    public function test_dashboard_exposes_only_aggregates_and_arabic_english_locales(): void
    {
        $user = $this->userWith(['reports.view']);

        $this->createRecord('locations', [
            'code' => 'PRIVATE-REF', 'location_type' => 'district', 'status' => 'active',
        ]);

        foreach (['ar', 'en'] as $locale) {
            $this->actingAs($user)->get("/{$locale}/operations/dashboard")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Operations/Dashboard')
                    ->where('locale', $locale)
                    ->where('summary.privacy_notice', fn ($value) => is_string($value) && $value !== '')
                    ->where('summary.gates.institutional_approvals_enabled', false)
                    ->where('summary.gates.beneficiary_records_enabled', false)
                    ->where('summary.gates.sensitive_disaggregation_enabled', false)
                    ->where('summary.gates.inventory_valuation_enabled', false)
                    ->etc());
        }
    }

    /** @param array<string, mixed> $attributes */
    private function createRecord(string $resource, array $attributes): int
    {
        $table = config("operations.resources.{$resource}.table");
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        return (int) DB::table($table)->insertGetId($attributes);
    }

    /** @param list<string> $keys */
    private function userWith(array $keys): User
    {
        $user = User::factory()->create();
        $role = Role::create(['key' => 'operation-'.($user->id), 'name' => 'Operator']);

        $permissionCatalog = config('permissions', []);

        foreach ($keys as $key) {
            $definition = is_array($permissionCatalog[$key] ?? null) ? $permissionCatalog[$key] : [];
            $permission = Permission::firstOrCreate([
                'key' => $key,
                'name' => $definition['label'] ?? $key,
                'module' => $definition['module'] ?? 'operations',
                'is_protected' => false,
                'is_retired' => false,
            ]);
            $role->permissions()->attach($permission);
        }

        app(AccessManagementService::class)->syncRoles($user, [$role->id]);

        return $user;
    }
}
