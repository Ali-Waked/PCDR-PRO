<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase-4 operational rehearsal dataset.
 *
 * All rows are internally consistent fictional records: no beneficiaries, no
 * approval gates, no real people and no real organisations. Append-only tables
 * are inserted once and never updated. The central demo seeder owns the
 * seedability assertion and the manifest lifecycle.
 */
final class DemoOperationsSeeder extends Seeder
{
    /** @var list<string> */
    private const USER_EMAILS = [
        'layla.nasser@pcdr.test',
        'sami.haddad@pcdr.test',
        'hana.rafiq@pcdr.test',
        'karim.zuhair@pcdr.test',
        'nadia.qamar@pcdr.test',
        'omar.faisal@pcdr.test',
        'reem.adwan@pcdr.test',
        'tareq.shukri@pcdr.test',
    ];

    /** @var list<string> */
    private const MEDIA_SLUGS = [
        'health-check',
        'food-handover',
        'classroom',
        'lecture',
        'donation-sorting',
        'food-donations',
        'food-packing',
        'handwashing',
        'water-well',
        'community-garden',
        'vegetable-harvest',
        'planting',
    ];

    /** @var list<array{code: string, parent: int|null, type: string, status: string}> */
    private const LOCATIONS = [
        ['code' => 'LOC-001', 'parent' => null, 'type' => 'country', 'status' => 'active'],
        ['code' => 'LOC-002', 'parent' => 1, 'type' => 'governorate', 'status' => 'active'],
        ['code' => 'LOC-003', 'parent' => 1, 'type' => 'governorate', 'status' => 'active'],
        ['code' => 'LOC-004', 'parent' => 2, 'type' => 'district', 'status' => 'active'],
        ['code' => 'LOC-005', 'parent' => 2, 'type' => 'district', 'status' => 'active'],
        ['code' => 'LOC-006', 'parent' => 3, 'type' => 'district', 'status' => 'active'],
        ['code' => 'LOC-007', 'parent' => 4, 'type' => 'site', 'status' => 'active'],
        ['code' => 'LOC-008', 'parent' => 6, 'type' => 'site', 'status' => 'inactive'],
    ];

    /** @var list<array{code: string, category: string, unit: string, sku: string, threshold: string, status: string}> */
    private const INVENTORY_ITEMS = [
        ['code' => 'INV-001', 'category' => 'food', 'unit' => 'kg', 'sku' => 'SKU-INV-001', 'threshold' => '120.000', 'status' => 'active'],
        ['code' => 'INV-002', 'category' => 'food', 'unit' => 'kg', 'sku' => 'SKU-INV-002', 'threshold' => '80.000', 'status' => 'active'],
        ['code' => 'INV-003', 'category' => 'shelter', 'unit' => 'kit', 'sku' => 'SKU-INV-003', 'threshold' => '40.000', 'status' => 'active'],
        ['code' => 'INV-004', 'category' => 'medical', 'unit' => 'kit', 'sku' => 'SKU-INV-004', 'threshold' => '25.000', 'status' => 'active'],
        ['code' => 'INV-005', 'category' => 'water', 'unit' => 'litre', 'sku' => 'SKU-INV-005', 'threshold' => '200.000', 'status' => 'active'],
        ['code' => 'INV-006', 'category' => 'education', 'unit' => 'kit', 'sku' => 'SKU-INV-006', 'threshold' => '35.000', 'status' => 'active'],
        ['code' => 'INV-007', 'category' => 'shelter', 'unit' => 'piece', 'sku' => 'SKU-INV-007', 'threshold' => '50.000', 'status' => 'active'],
        ['code' => 'INV-008', 'category' => 'food', 'unit' => 'box', 'sku' => 'SKU-INV-008', 'threshold' => '60.000', 'status' => 'active'],
        ['code' => 'INV-009', 'category' => 'medical', 'unit' => 'box', 'sku' => 'SKU-INV-009', 'threshold' => '30.000', 'status' => 'active'],
        ['code' => 'INV-010', 'category' => 'other', 'unit' => 'unit', 'sku' => 'SKU-INV-010', 'threshold' => '10.000', 'status' => 'inactive'],
    ];

    /** @var list<array{code: string, category: string, program: int, project: int, location: int, planned_start: string, planned_end: string, actual_start: string|null, actual_end: string|null, planned_reach: int, verified_reach: int, status: string}> */
    private const INTERVENTIONS = [
        ['code' => 'INT-001', 'category' => 'relief', 'program' => 1, 'project' => 1, 'location' => 2, 'planned_start' => '2026-08-25', 'planned_end' => '2026-10-20', 'actual_start' => '2026-08-28', 'actual_end' => null, 'planned_reach' => 450, 'verified_reach' => 320, 'status' => 'active'],
        ['code' => 'INT-002', 'category' => 'livelihood', 'program' => 2, 'project' => 2, 'location' => 3, 'planned_start' => '2026-08-26', 'planned_end' => '2026-11-15', 'actual_start' => '2026-08-30', 'actual_end' => null, 'planned_reach' => 180, 'verified_reach' => 120, 'status' => 'active'],
        ['code' => 'INT-003', 'category' => 'protection', 'program' => 3, 'project' => 3, 'location' => 4, 'planned_start' => '2026-09-15', 'planned_end' => '2026-12-10', 'actual_start' => null, 'actual_end' => null, 'planned_reach' => 220, 'verified_reach' => 0, 'status' => 'on_hold'],
        ['code' => 'INT-004', 'category' => 'water_sanitation', 'program' => 4, 'project' => 4, 'location' => 5, 'planned_start' => '2026-08-01', 'planned_end' => '2026-09-15', 'actual_start' => '2026-08-03', 'actual_end' => '2026-08-30', 'planned_reach' => 600, 'verified_reach' => 575, 'status' => 'completed'],
        ['code' => 'INT-005', 'category' => 'education', 'program' => 5, 'project' => 5, 'location' => 6, 'planned_start' => '2026-07-01', 'planned_end' => '2026-08-30', 'actual_start' => '2026-07-05', 'actual_end' => '2026-08-28', 'planned_reach' => 300, 'verified_reach' => 290, 'status' => 'archived'],
        ['code' => 'INT-006', 'category' => 'health', 'program' => 6, 'project' => 6, 'location' => 7, 'planned_start' => '2026-09-20', 'planned_end' => '2026-10-30', 'actual_start' => null, 'actual_end' => null, 'planned_reach' => 150, 'verified_reach' => 0, 'status' => 'cancelled'],
    ];

    /** @var list<array{code: string, project: int, intervention: int, location: int, activity_type: string, planned_start: string, planned_end: string, actual_start: string|null, actual_end: string|null, progress: int, status: string}> */
    private const ACTIVITIES = [
        ['code' => 'ACT-001', 'project' => 1, 'intervention' => 1, 'location' => 2, 'activity_type' => 'activity', 'planned_start' => '2026-08-20', 'planned_end' => '2026-08-30', 'actual_start' => '2026-08-21', 'actual_end' => '2026-08-29', 'progress' => 100, 'status' => 'completed'],
        ['code' => 'ACT-002', 'project' => 1, 'intervention' => 1, 'location' => 3, 'activity_type' => 'activity', 'planned_start' => '2026-08-25', 'planned_end' => '2026-09-10', 'actual_start' => '2026-08-27', 'actual_end' => null, 'progress' => 55, 'status' => 'in_progress'],
        ['code' => 'ACT-003', 'project' => 2, 'intervention' => 2, 'location' => 3, 'activity_type' => 'milestone', 'planned_start' => '2026-10-06', 'planned_end' => '2026-10-10', 'actual_start' => null, 'actual_end' => null, 'progress' => 0, 'status' => 'planned'],
        ['code' => 'ACT-004', 'project' => 2, 'intervention' => 2, 'location' => 4, 'activity_type' => 'activity', 'planned_start' => '2026-08-15', 'planned_end' => '2026-09-05', 'actual_start' => '2026-08-17', 'actual_end' => null, 'progress' => 40, 'status' => 'in_progress'],
        ['code' => 'ACT-005', 'project' => 3, 'intervention' => 3, 'location' => 4, 'activity_type' => 'activity', 'planned_start' => '2026-10-01', 'planned_end' => '2026-10-25', 'actual_start' => null, 'actual_end' => null, 'progress' => 0, 'status' => 'planned'],
        ['code' => 'ACT-006', 'project' => 3, 'intervention' => 3, 'location' => 5, 'activity_type' => 'milestone', 'planned_start' => '2026-11-01', 'planned_end' => '2026-11-05', 'actual_start' => null, 'actual_end' => null, 'progress' => 0, 'status' => 'draft'],
        ['code' => 'ACT-007', 'project' => 4, 'intervention' => 4, 'location' => 5, 'activity_type' => 'activity', 'planned_start' => '2026-08-05', 'planned_end' => '2026-08-25', 'actual_start' => '2026-08-05', 'actual_end' => '2026-08-24', 'progress' => 100, 'status' => 'completed'],
        ['code' => 'ACT-008', 'project' => 5, 'intervention' => 5, 'location' => 6, 'activity_type' => 'activity', 'planned_start' => '2026-08-10', 'planned_end' => '2026-08-20', 'actual_start' => '2026-08-10', 'actual_end' => '2026-08-12', 'progress' => 20, 'status' => 'cancelled'],
        ['code' => 'ACT-009', 'project' => 6, 'intervention' => 6, 'location' => 7, 'activity_type' => 'activity', 'planned_start' => '2026-09-22', 'planned_end' => '2026-10-12', 'actual_start' => '2026-09-23', 'actual_end' => null, 'progress' => 65, 'status' => 'in_progress'],
        ['code' => 'ACT-010', 'project' => 7, 'intervention' => 4, 'location' => 8, 'activity_type' => 'milestone', 'planned_start' => '2026-11-10', 'planned_end' => '2026-11-15', 'actual_start' => null, 'actual_end' => null, 'progress' => 0, 'status' => 'draft'],
    ];

    /** @var list<array{code: string, project: int, intervention: int, location: int, assistance_type: string, planned_date: string, responsible: int, status: string}> */
    private const DISTRIBUTION_PLANS = [
        ['code' => 'DPL-001', 'project' => 1, 'intervention' => 1, 'location' => 2, 'assistance_type' => 'food_parcel', 'planned_date' => '2026-09-18', 'responsible' => 1, 'status' => 'scheduled'],
        ['code' => 'DPL-002', 'project' => 2, 'intervention' => 2, 'location' => 3, 'assistance_type' => 'shelter_kit', 'planned_date' => '2026-10-02', 'responsible' => 2, 'status' => 'in_progress'],
        ['code' => 'DPL-003', 'project' => 4, 'intervention' => 4, 'location' => 5, 'assistance_type' => 'water_supply', 'planned_date' => '2026-09-08', 'responsible' => 3, 'status' => 'reconciled'],
        ['code' => 'DPL-004', 'project' => 6, 'intervention' => 6, 'location' => 7, 'assistance_type' => 'medical_kit', 'planned_date' => '2026-10-20', 'responsible' => 4, 'status' => 'cancelled'],
    ];

    /** @var list<array{code: string, plan: int, scheduled_at: string, location: int, responsible: int, status: string}> */
    private const DISTRIBUTION_ROUNDS = [
        ['code' => 'DR-001', 'plan' => 1, 'scheduled_at' => '2026-09-18 08:00:00', 'location' => 2, 'responsible' => 1, 'status' => 'scheduled'],
        ['code' => 'DR-002', 'plan' => 1, 'scheduled_at' => '2026-09-19 08:00:00', 'location' => 4, 'responsible' => 2, 'status' => 'completed'],
        ['code' => 'DR-003', 'plan' => 2, 'scheduled_at' => '2026-10-02 09:00:00', 'location' => 3, 'responsible' => 3, 'status' => 'in_progress'],
        ['code' => 'DR-004', 'plan' => 2, 'scheduled_at' => '2026-10-03 09:00:00', 'location' => 5, 'responsible' => 4, 'status' => 'scheduled'],
        ['code' => 'DR-005', 'plan' => 3, 'scheduled_at' => '2026-09-08 07:30:00', 'location' => 5, 'responsible' => 5, 'status' => 'reconciled'],
        ['code' => 'DR-006', 'plan' => 3, 'scheduled_at' => '2026-09-09 07:30:00', 'location' => 6, 'responsible' => 6, 'status' => 'completed'],
        ['code' => 'DR-007', 'plan' => 4, 'scheduled_at' => '2026-10-20 10:00:00', 'location' => 7, 'responsible' => 7, 'status' => 'cancelled'],
        ['code' => 'DR-008', 'plan' => 4, 'scheduled_at' => '2026-10-21 10:00:00', 'location' => 8, 'responsible' => 8, 'status' => 'planned'],
    ];

    /** @var list<string> */
    private const DISTRIBUTION_RECORD_STATUSES = ['planned', 'recorded', 'verified', 'reconciled'];

    /** @var list<array{code: string, location: int, responsible: int, status: string, capacity_note: string}> */
    private const WAREHOUSES = [
        ['code' => 'WH-001', 'location' => 2, 'responsible' => 1, 'status' => 'active', 'capacity_note' => 'مخزن رئيسي للمواد الغذائية والمأوى.'],
        ['code' => 'WH-002', 'location' => 3, 'responsible' => 2, 'status' => 'inactive', 'capacity_note' => 'مخزن احتياطي مغلق مؤقتا لإعادة الترتيب.'],
        ['code' => 'WH-003', 'location' => 5, 'responsible' => 3, 'status' => 'active', 'capacity_note' => 'مخزن ميداني للمياه والمواد الطبية.'],
    ];

    /** @var list<array{reference: string, location: int, skills: string, availability: string, status: string}> */
    private const VOLUNTEERS = [
        ['reference' => 'VOL-001', 'location' => 2, 'skills' => 'Logistics; inventory counting', 'availability' => 'weekday mornings', 'status' => 'active'],
        ['reference' => 'VOL-002', 'location' => 3, 'skills' => 'Community mobilization', 'availability' => 'weekends', 'status' => 'active'],
        ['reference' => 'VOL-003', 'location' => 4, 'skills' => 'Basic first aid', 'availability' => 'evenings', 'status' => 'registered'],
        ['reference' => 'VOL-004', 'location' => 5, 'skills' => 'Data entry; reporting', 'availability' => 'weekday afternoons', 'status' => 'active'],
        ['reference' => 'VOL-005', 'location' => 6, 'skills' => 'Site coordination', 'availability' => 'weekends', 'status' => 'inactive'],
        ['reference' => 'VOL-006', 'location' => 7, 'skills' => 'Translation support', 'availability' => 'evenings', 'status' => 'active'],
        ['reference' => 'VOL-007', 'location' => 2, 'skills' => 'Warehouse packing', 'availability' => 'weekday mornings', 'status' => 'suspended'],
        ['reference' => 'VOL-008', 'location' => 3, 'skills' => 'Photography; documentation', 'availability' => 'weekends', 'status' => 'active'],
        ['reference' => 'VOL-009', 'location' => 4, 'skills' => 'Survey enumeration', 'availability' => 'weekday afternoons', 'status' => 'archived'],
        ['reference' => 'VOL-010', 'location' => 5, 'skills' => 'Transport coordination', 'availability' => 'evenings', 'status' => 'registered'],
        ['reference' => 'VOL-011', 'location' => 6, 'skills' => 'Listing and filing', 'availability' => 'weekends', 'status' => 'active'],
        ['reference' => 'VOL-012', 'location' => 7, 'skills' => 'Community liaison', 'availability' => 'weekday mornings', 'status' => 'potential'],
    ];

    /** @var list<array{code: string, team_type: string, leader: int, location: int, skills: string, status: string}> */
    private const VOLUNTEER_TEAMS = [
        ['code' => 'VT-001', 'team_type' => 'field', 'leader' => 1, 'location' => 2, 'skills' => 'Distribution; community liaison', 'status' => 'active'],
        ['code' => 'VT-002', 'team_type' => 'logistics', 'leader' => 4, 'location' => 3, 'skills' => 'Warehousing; inventory counting', 'status' => 'active'],
        ['code' => 'VT-003', 'team_type' => 'medical', 'leader' => 3, 'location' => 4, 'skills' => 'Basic first aid; referral', 'status' => 'inactive'],
        ['code' => 'VT-004', 'team_type' => 'community', 'leader' => 6, 'location' => 7, 'skills' => 'Translation; feedback collection', 'status' => 'archived'],
    ];

    /** @var list<string> */
    private const TEAM_MEMBER_ROLES = ['Team lead', 'Logistics support', 'Community liaison'];

    /** @var list<string> */
    private const ASSIGNMENT_STATUSES = ['planned', 'active', 'completed', 'cancelled'];

    /** @var list<array{code: string, provider: string, training_date: string, hours: string, status: string}> */
    private const VOLUNTEER_TRAININGS = [
        ['code' => 'VTR-001', 'provider' => 'وحدة التطوير المؤسسي', 'training_date' => '2026-08-12', 'hours' => '6.00', 'status' => 'completed'],
        ['code' => 'VTR-002', 'provider' => 'وحدة الجودة والتطوير', 'training_date' => '2026-08-19', 'hours' => '4.50', 'status' => 'completed'],
        ['code' => 'VTR-003', 'provider' => 'Field coordination unit', 'training_date' => '2026-09-25', 'hours' => '3.00', 'status' => 'planned'],
        ['code' => 'VTR-004', 'provider' => 'Field coordination unit', 'training_date' => '2026-10-05', 'hours' => '2.00', 'status' => 'cancelled'],
    ];

    /** @var list<array{code: string, committee_type: string, location: int, status: string}> */
    private const COMMITTEES = [
        ['code' => 'COM-001', 'committee_type' => 'committee', 'location' => 2, 'status' => 'established'],
        ['code' => 'COM-002', 'committee_type' => 'committee', 'location' => 4, 'status' => 'active'],
        ['code' => 'COM-003', 'committee_type' => 'initiative', 'location' => 6, 'status' => 'inactive'],
    ];

    /** @var list<string> */
    private const COMMITTEE_MEMBER_ROLES = ['Chair (internal)', 'Records support', 'Community liaison'];

    /** @var list<array{code: string, committee: int, meeting_date: string, minutes: string, decisions: string, status: string}> */
    private const COMMITTEE_MEETINGS = [
        ['code' => 'CMT-001', 'committee' => 1, 'meeting_date' => '2026-09-04', 'minutes' => 'تمت مراجعة خطة التوزيع ونقاط التنسيق.', 'decisions' => 'اعتماد جدول جرد أسبوعي للمخزون.', 'status' => 'held'],
        ['code' => 'CMT-002', 'committee' => 1, 'meeting_date' => '2026-09-18', 'minutes' => 'مراجعة ملاحظات غير محددة الهوية حول جولة التوزيع.', 'decisions' => 'رفع ملخص الملاحظات إلى وحدة الجودة.', 'status' => 'held'],
        ['code' => 'CMT-003', 'committee' => 2, 'meeting_date' => '2026-09-22', 'minutes' => 'جدولة جولة ميدانية للتأكد من جاهزية الموقع.', 'decisions' => 'تأجيل الجولة إلى الأسبوع التالي.', 'status' => 'scheduled'],
        ['code' => 'CMT-004', 'committee' => 2, 'meeting_date' => '2026-08-28', 'minutes' => 'مراجعة مسار التحويل وإجراءات الجودة.', 'decisions' => 'اعتماد خطوات التحقق الميداني.', 'status' => 'held'],
        ['code' => 'CMT-005', 'committee' => 3, 'meeting_date' => '2026-09-30', 'minutes' => 'اجتماع ملغي بسبب تعارض جدول الأنشطة.', 'decisions' => 'لا قرارات.', 'status' => 'cancelled'],
        ['code' => 'CMT-006', 'committee' => 3, 'meeting_date' => '2026-10-14', 'minutes' => 'جدولة مراجعة مؤشرات الجودة.', 'decisions' => 'تجهيز ملخص المؤشرات قبل الاجتماع.', 'status' => 'scheduled'],
    ];

    /** @var list<array{category: string, summary: string, status: string}> */
    private const COMMUNITY_FEEDBACK = [
        ['category' => 'service_quality', 'summary' => 'ملاحظة غير محددة الهوية حول وضوح تعليمات التوزيع.', 'status' => 'new'],
        ['category' => 'accessibility', 'summary' => 'اقتراح بتوفير مسار وصول أكثر وضوحا إلى نقطة الاستلام.', 'status' => 'under_review'],
        ['category' => 'timing', 'summary' => 'ملاحظة حول توقيت الجولة الميدانية.', 'status' => 'responded'],
        ['category' => 'communication', 'summary' => 'طلب تبسيط لغة الإشعارات الخاصة بالأنشطة.', 'status' => 'closed'],
        ['category' => 'service_quality', 'summary' => 'ملاحظة حول جودة التغليف في إحدى الجولات.', 'status' => 'new'],
        ['category' => 'accessibility', 'summary' => 'اقتراح بإضافة لافتة توضيحية داخل الموقع.', 'status' => 'under_review'],
        ['category' => 'timing', 'summary' => 'ملاحظة حول توزيع المهام بين الفترتين الصباحية والمسائية.', 'status' => 'responded'],
        ['category' => 'communication', 'summary' => 'طلب توحيد استمارة الملاحظات الميدانية.', 'status' => 'closed'],
    ];

    /** @var list<array{code: string, partner_type: string, organization_type: string, relationship_status: string, internal_notes: string}> */
    private const PARTNERS = [
        ['code' => 'PART-001', 'partner_type' => 'partner', 'organization_type' => 'local_cooperative', 'relationship_status' => 'contacted', 'internal_notes' => 'جهة تعاون محلية تدعم التنسيق الميداني.'],
        ['code' => 'PART-002', 'partner_type' => 'donor', 'organization_type' => 'donor_office', 'relationship_status' => 'contacted', 'internal_notes' => 'مكتب مانح متعاون مع برامج الأمن الغذائي.'],
        ['code' => 'PART-003', 'partner_type' => 'partner', 'organization_type' => 'community_circle', 'relationship_status' => 'potential', 'internal_notes' => 'حلقة مجتمعية تشارك في مراجعة الجولات الميدانية.'],
        ['code' => 'PART-004', 'partner_type' => 'both', 'organization_type' => 'technical_circle', 'relationship_status' => 'in_discussion', 'internal_notes' => 'جهة متخصصة لتقديم الدعم التقني وبناء القدرات.'],
        ['code' => 'PART-005', 'partner_type' => 'partner', 'organization_type' => 'logistics_circle', 'relationship_status' => 'active', 'internal_notes' => 'شريك متعاون لنقل المواد بين المخازن ونقاط التوزيع.'],
        ['code' => 'PART-006', 'partner_type' => 'partner', 'organization_type' => 'learning_circle', 'relationship_status' => 'inactive', 'internal_notes' => 'جهة شريكة أوقفت المشاركة مؤقتا.'],
        ['code' => 'PART-007', 'partner_type' => 'donor', 'organization_type' => 'donor_fund', 'relationship_status' => 'closed', 'internal_notes' => 'صندوق مانح أغلق ملفه بعد انتهاء مدة الاتفاق.'],
        ['code' => 'PART-008', 'partner_type' => 'partner', 'organization_type' => 'advisory_circle', 'relationship_status' => 'archived', 'internal_notes' => 'حلقة استشارية مؤرشفة بعد انتهاء الأعمال.'],
    ];

    /** @var list<string> */
    private const PARTNER_PROJECT_RELATIONSHIPS = ['implementation', 'funding', 'technical_support'];

    /** @var list<string> */
    private const PARTNER_AGREEMENT_STATUSES = ['draft', 'active', 'expired', 'terminated', 'archived', 'active'];

    /** @var list<string> */
    private const PARTNER_CONTACT_ROLES = ['Coordination desk', 'Field desk', 'Documentation desk'];

    /** @var list<array{reference: string, partner: int, category: string, min_amount: string, max_amount: string, opens_at: string, closes_at: string, status: string}> */
    private const GRANT_OPPORTUNITIES = [
        ['reference' => 'GO-001', 'partner' => 2, 'category' => 'relief', 'min_amount' => '10000.00', 'max_amount' => '80000.00', 'opens_at' => '2026-09-01', 'closes_at' => '2026-10-15', 'status' => 'open'],
        ['reference' => 'GO-002', 'partner' => 4, 'category' => 'capacity_building', 'min_amount' => '5000.00', 'max_amount' => '30000.00', 'opens_at' => '2026-09-05', 'closes_at' => '2026-10-30', 'status' => 'open'],
        ['reference' => 'GO-003', 'partner' => 7, 'category' => 'meal', 'min_amount' => '20000.00', 'max_amount' => '120000.00', 'opens_at' => '2026-08-01', 'closes_at' => '2026-09-10', 'status' => 'closed'],
        ['reference' => 'GO-004', 'partner' => 2, 'category' => 'water_sanitation', 'min_amount' => '15000.00', 'max_amount' => '90000.00', 'opens_at' => '2026-09-10', 'closes_at' => '2026-11-05', 'status' => 'cancelled'],
        ['reference' => 'GO-005', 'partner' => 4, 'category' => 'education', 'min_amount' => '8000.00', 'max_amount' => '45000.00', 'opens_at' => '2026-09-15', 'closes_at' => '2026-11-20', 'status' => 'open'],
    ];

    /** @var list<array{code: string, opportunity: int, program: int, project: int, budget_amount: string, deadline: string, status: string}> */
    private const GRANT_PROPOSALS = [
        ['code' => 'GP-001', 'opportunity' => 1, 'program' => 1, 'project' => 1, 'budget_amount' => '72000.00', 'deadline' => '2026-10-10', 'status' => 'identified'],
        ['code' => 'GP-002', 'opportunity' => 2, 'program' => 2, 'project' => 2, 'budget_amount' => '26000.00', 'deadline' => '2026-10-25', 'status' => 'under_assessment'],
        ['code' => 'GP-003', 'opportunity' => 3, 'program' => 3, 'project' => 3, 'budget_amount' => '88000.00', 'deadline' => '2026-09-05', 'status' => 'drafting'],
        ['code' => 'GP-004', 'opportunity' => 5, 'program' => 4, 'project' => 4, 'budget_amount' => '39000.00', 'deadline' => '2026-11-10', 'status' => 'internal_review'],
        ['code' => 'GP-005', 'opportunity' => 1, 'program' => 5, 'project' => 5, 'budget_amount' => '54000.00', 'deadline' => '2026-10-12', 'status' => 'submitted'],
        ['code' => 'GP-006', 'opportunity' => 2, 'program' => 6, 'project' => 6, 'budget_amount' => '21000.00', 'deadline' => '2026-10-20', 'status' => 'awarded'],
    ];

    /** @var list<array{reference: string, donor: int, proposal: int, project: int, amount: string, start_date: string, end_date: string, status: string}> */
    private const GRANT_AWARDS = [
        ['reference' => 'GA-001', 'donor' => 2, 'proposal' => 6, 'project' => 6, 'amount' => '21000.00', 'start_date' => '2026-10-01', 'end_date' => '2027-03-31', 'status' => 'awarded'],
        ['reference' => 'GA-002', 'donor' => 4, 'proposal' => 3, 'project' => 3, 'amount' => '85000.00', 'start_date' => '2026-09-01', 'end_date' => '2027-02-28', 'status' => 'active'],
        ['reference' => 'GA-003', 'donor' => 7, 'proposal' => 1, 'project' => 1, 'amount' => '60000.00', 'start_date' => '2026-01-01', 'end_date' => '2026-08-31', 'status' => 'completed'],
        ['reference' => 'GA-004', 'donor' => 2, 'proposal' => 2, 'project' => 2, 'amount' => '24000.00', 'start_date' => '2026-11-01', 'end_date' => '2027-04-30', 'status' => 'draft'],
    ];

    /** @var list<array{code: string, program: int, project: int, status: string, theory_of_change: string}> */
    private const RESULTS_FRAMEWORKS = [
        ['code' => 'RF-001', 'program' => 1, 'project' => 1, 'status' => 'active', 'theory_of_change' => 'إذا توفرت مواد الإغاثة في الوقت المناسب فإن جودة التوزيع تتحسن.'],
        ['code' => 'RF-002', 'program' => 2, 'project' => 2, 'status' => 'active', 'theory_of_change' => 'إذا تحسنت إجراءات التنسيق الميداني فإن تنفيذ الأنشطة يصبح أكثر انتظاما.'],
        ['code' => 'RF-003', 'program' => 3, 'project' => 4, 'status' => 'draft', 'theory_of_change' => 'إذا تم توثيق الملاحظات غير المحددة الهوية فإن قرارات الجودة تصبح أكثر وضوحا.'],
        ['code' => 'RF-004', 'program' => 5, 'project' => 5, 'status' => 'archived', 'theory_of_change' => 'إذا استخدمت مؤشرات موحدة فإن مقارنة الجولات تصبح ممكنة.'],
    ];

    /** @var list<array{code: string, start_date: string, end_date: string, status: string}> */
    private const REPORTING_PERIODS = [
        ['code' => 'RP-001', 'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'status' => 'closed'],
        ['code' => 'RP-002', 'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'status' => 'open'],
        ['code' => 'RP-003', 'start_date' => '2027-01-01', 'end_date' => '2027-03-31', 'status' => 'open'],
        ['code' => 'RP-004', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'status' => 'archived'],
    ];

    /** @var list<array{code: string, indicator_type: string, unit: string, baseline: string, target: string, calculation_rule: string, status: string}> */
    private const INDICATORS = [
        ['code' => 'IND-001', 'indicator_type' => 'output', 'unit' => 'kit', 'baseline' => '0.0000', 'target' => '500.0000', 'calculation_rule' => 'عدد الطرود الموزعة مقسوما على عدد الطرود المخططة.', 'status' => 'active'],
        ['code' => 'IND-002', 'indicator_type' => 'outcome', 'unit' => 'percent', 'baseline' => '0.0000', 'target' => '85.0000', 'calculation_rule' => 'نسبة الجولات التي استكملت التحقق خلال المدة المقررة.', 'status' => 'active'],
        ['code' => 'IND-003', 'indicator_type' => 'impact', 'unit' => 'score', 'baseline' => '2.0000', 'target' => '4.0000', 'calculation_rule' => 'متوسط تقييم جودة الخدمة في استمارة المتابعة.', 'status' => 'draft'],
        ['code' => 'IND-004', 'indicator_type' => 'output', 'unit' => 'activity', 'baseline' => '0.0000', 'target' => '10.0000', 'calculation_rule' => 'عدد الأنشطة المكتملة مقابل الخطة.', 'status' => 'active'],
        ['code' => 'IND-005', 'indicator_type' => 'output', 'unit' => 'litre', 'baseline' => '0.0000', 'target' => '9000.0000', 'calculation_rule' => 'إجمالي المياه المسجلة في الجولات.', 'status' => 'active'],
        ['code' => 'IND-006', 'indicator_type' => 'outcome', 'unit' => 'percent', 'baseline' => '0.0000', 'target' => '75.0000', 'calculation_rule' => 'نسبة الملاحظات التي تم الرد عليها.', 'status' => 'active'],
        ['code' => 'IND-007', 'indicator_type' => 'output', 'unit' => 'training', 'baseline' => '0.0000', 'target' => '4.0000', 'calculation_rule' => 'عدد جلسات بناء القدرات المكتملة.', 'status' => 'draft'],
        ['code' => 'IND-008', 'indicator_type' => 'outcome', 'unit' => 'percent', 'baseline' => '0.0000', 'target' => '90.0000', 'calculation_rule' => 'نسبة الجرد المطابق للسجلات بعد المراجعة.', 'status' => 'active'],
        ['code' => 'IND-009', 'indicator_type' => 'impact', 'unit' => 'score', 'baseline' => '1.0000', 'target' => '3.5000', 'calculation_rule' => 'متوسط درجة جاهزية الموقع في التقييم الميداني.', 'status' => 'archived'],
        ['code' => 'IND-010', 'indicator_type' => 'output', 'unit' => 'agreement', 'baseline' => '0.0000', 'target' => '6.0000', 'calculation_rule' => 'عدد الاتفاقيات النشطة في السجل.', 'status' => 'active'],
        ['code' => 'IND-011', 'indicator_type' => 'outcome', 'unit' => 'percent', 'baseline' => '0.0000', 'target' => '80.0000', 'calculation_rule' => 'نسبة المقترحات المقدمة في الموعد المحدد.', 'status' => 'draft'],
        ['code' => 'IND-012', 'indicator_type' => 'output', 'unit' => 'report', 'baseline' => '0.0000', 'target' => '6.0000', 'calculation_rule' => 'عدد التقارير التشغيلية المولدة.', 'status' => 'active'],
    ];

    /** @var list<string> */
    private const MEASUREMENT_STATUSES = [
        'draft', 'submitted', 'under_verification', 'verified', 'reported', 'rejected',
    ];

    /** @var list<array{code: string, project: int, evaluation_type: string, status: string}> */
    private const EVALUATIONS = [
        ['code' => 'EVAL-001', 'project' => 1, 'evaluation_type' => 'midterm', 'status' => 'in_progress'],
        ['code' => 'EVAL-002', 'project' => 2, 'evaluation_type' => 'final', 'status' => 'completed'],
        ['code' => 'EVAL-003', 'project' => 3, 'evaluation_type' => 'process', 'status' => 'archived'],
        ['code' => 'EVAL-004', 'project' => 4, 'evaluation_type' => 'impact', 'status' => 'planned'],
    ];

    /** @var list<array{code: string, assessment_type: string, need_category: string, methodology_type: string, target_population_definition: string, location: int, program: int, project: int, status: string}> */
    private const ASSESSMENTS = [
        ['code' => 'ASM-001', 'assessment_type' => 'assessment', 'need_category' => 'food_security', 'methodology_type' => 'field_interviews', 'target_population_definition' => 'فئة المشاركين في الأنشطة الميدانية.', 'location' => 2, 'program' => 1, 'project' => 1, 'status' => 'draft'],
        ['code' => 'ASM-002', 'assessment_type' => 'research', 'need_category' => 'service_quality', 'methodology_type' => 'desk_review', 'target_population_definition' => 'سجلات مجهولة الهوية ضمن نطاق المراجعة.', 'location' => 3, 'program' => 2, 'project' => 2, 'status' => 'under_review'],
        ['code' => 'ASM-003', 'assessment_type' => 'assessment', 'need_category' => 'shelter', 'methodology_type' => 'site_observation', 'target_population_definition' => 'مواقع محددة للمراجعة الميدانية.', 'location' => 4, 'program' => 3, 'project' => 3, 'status' => 'published'],
        ['code' => 'ASM-004', 'assessment_type' => 'research', 'need_category' => 'water_sanitation', 'methodology_type' => 'register_review', 'target_population_definition' => 'جولات توزيع المياه ضمن خطة التشغيل.', 'location' => 5, 'program' => 4, 'project' => 4, 'status' => 'archived'],
    ];

    /** @var list<array{code: string, instrument_type: string, version: string, status: string}> */
    private const DATA_COLLECTION_INSTRUMENTS = [
        ['code' => 'DCI-001', 'instrument_type' => 'survey', 'version' => '1.0', 'status' => 'active'],
        ['code' => 'DCI-002', 'instrument_type' => 'interview_guide', 'version' => '1.0', 'status' => 'active'],
        ['code' => 'DCI-003', 'instrument_type' => 'focus_group_checklist', 'version' => '1.1', 'status' => 'draft'],
        ['code' => 'DCI-004', 'instrument_type' => 'observation_form', 'version' => '1.0', 'status' => 'active'],
        ['code' => 'DCI-005', 'instrument_type' => 'register_review', 'version' => '2.0', 'status' => 'archived'],
        ['code' => 'DCI-006', 'instrument_type' => 'key_informant_interview', 'version' => '1.0', 'status' => 'draft'],
    ];

    /** @var list<array{code: string, category: string, program: int, project: int, location: int, status_filter: string, format: string, status: string}> */
    private const OPERATIONAL_REPORTS = [
        ['code' => 'OREP-001', 'category' => 'programs', 'program' => 1, 'project' => 1, 'location' => 2, 'status_filter' => 'active', 'format' => 'html', 'status' => 'generated'],
        ['code' => 'OREP-002', 'category' => 'distributions', 'program' => 1, 'project' => 2, 'location' => 3, 'status_filter' => 'reconciled', 'format' => 'csv', 'status' => 'generated'],
        ['code' => 'OREP-003', 'category' => 'inventory', 'program' => 2, 'project' => 3, 'location' => 4, 'status_filter' => 'active', 'format' => 'html', 'status' => 'draft'],
        ['code' => 'OREP-004', 'category' => 'volunteers', 'program' => 3, 'project' => 4, 'location' => 5, 'status_filter' => 'active', 'format' => 'html', 'status' => 'generated'],
        ['code' => 'OREP-005', 'category' => 'grants', 'program' => 4, 'project' => 5, 'location' => 6, 'status_filter' => 'submitted', 'format' => 'csv', 'status' => 'archived'],
        ['code' => 'OREP-006', 'category' => 'meal', 'program' => 5, 'project' => 6, 'location' => 7, 'status_filter' => 'verified', 'format' => 'html', 'status' => 'draft'],
    ];

    /**
     * Twenty owners x two locales. All copy is fictional rehearsal wording.
     *
     * @var list<array{resource: string, index: int, ar: array<string, string>, en: array<string, string>}>
     */
    private const TRANSLATIONS = [
        ['resource' => 'inventory_items', 'index' => 1, 'ar' => ['name' => 'حبوب غذائية', 'summary' => 'مخزون من الحبوب الأساسية للجرد الميداني.'], 'en' => ['name' => 'Food grains', 'summary' => 'Stock of staple grains recorded for field counting.']],
        ['resource' => 'inventory_items', 'index' => 2, 'ar' => ['name' => 'زيت نباتي', 'summary' => 'مخزون من الزيت النباتي داخل المخازن.'], 'en' => ['name' => 'Cooking oil', 'summary' => 'Cooking-oil stock held in the warehouses.']],
        ['resource' => 'locations', 'index' => 1, 'ar' => ['title' => 'نطاق العمليات', 'name' => 'الإقليم الشمالي', 'summary' => 'منطقة تغطي العمليات الميدانية والتنسيق.'], 'en' => ['title' => 'Operations area', 'name' => 'Northern Region', 'summary' => 'An area coordinating field operations and distribution.']],
        ['resource' => 'locations', 'index' => 2, 'ar' => ['title' => 'محافظة الوادي', 'name' => 'وادي النخيل', 'summary' => 'وحدة جغرافية تضم نقاط التوزيع.'], 'en' => ['title' => 'Valley Governorate', 'name' => 'Palm Valley', 'summary' => 'A geographic unit containing distribution points.']],
        ['resource' => 'locations', 'index' => 3, 'ar' => ['title' => 'محافظة السهل', 'name' => 'السهل الخصب', 'summary' => 'وحدة جغرافية للأنشطة الميدانية.'], 'en' => ['title' => 'Plain Governorate', 'name' => 'Fertile Plain', 'summary' => 'A geographic unit for field activities.']],
        ['resource' => 'locations', 'index' => 4, 'ar' => ['title' => 'منطقة المركز', 'name' => 'مركز الوادي', 'summary' => 'موقع لتنسيق الجولات القريبة.'], 'en' => ['title' => 'Central District', 'name' => 'Valley Centre', 'summary' => 'A site for coordinating nearby rounds.']],
        ['resource' => 'interventions', 'index' => 1, 'ar' => ['title' => 'توزيع مواد الإغاثة الأساسية', 'summary' => 'توزيع مواد غذائية ومأوى ضمن نطاق محدد.', 'lessons_learned' => 'التنسيق المبكر مع المخزن يقلل زمن الجولة.'], 'en' => ['title' => 'Basic relief distribution', 'summary' => 'A distribution of food and shelter supplies.', 'lessons_learned' => 'Early warehouse coordination shortens the round.']],
        ['resource' => 'interventions', 'index' => 2, 'ar' => ['title' => 'دعم سبل العيش', 'summary' => 'أنشطة لدعم سبل العيش في منطقة محددة.', 'lessons_learned' => 'توحيد نماذج التسجيل يسهل المراجعة.'], 'en' => ['title' => 'Livelihood support', 'summary' => 'Livelihood activities in the designated area.', 'lessons_learned' => 'Standard registration forms ease review.']],
        ['resource' => 'interventions', 'index' => 3, 'ar' => ['title' => 'ورشة الحماية الميدانية', 'summary' => 'مسار مؤجل لاستكمال الترتيبات.', 'lessons_learned' => 'تأجيل التنفيذ يمنع بيانات غير مكتملة.'], 'en' => ['title' => 'Field protection workshop', 'summary' => 'A postponed activity pending arrangements.', 'lessons_learned' => 'Postponement prevents incomplete records.']],
        ['resource' => 'interventions', 'index' => 4, 'ar' => ['title' => 'المياه والصرف الصحي', 'summary' => 'جولة مكتملة لتسجيل المياه في المخازن الميدانية.', 'lessons_learned' => 'الجرد قبل الجولة يمنع نقص الكميات.'], 'en' => ['title' => 'Water and sanitation', 'summary' => 'A completed round for recording water stocks.', 'lessons_learned' => 'Counting before the round avoids shortages.']],
        ['resource' => 'activities', 'index' => 1, 'ar' => ['title' => 'تجهيز طرود الإغاثة', 'summary' => 'نشاط لتجهيز الطرود قبل التوزيع.'], 'en' => ['title' => 'Packing relief parcels', 'summary' => 'An activity to prepare parcels before distribution.']],
        ['resource' => 'activities', 'index' => 2, 'ar' => ['title' => 'تسليم الجولة الأولى', 'summary' => 'نشاط ميداني جار لتسجيل الكميات المسلمة.'], 'en' => ['title' => 'First round handover', 'summary' => 'An ongoing field activity to record handed-over quantities.']],
        ['resource' => 'distribution_plans', 'index' => 1, 'ar' => ['title' => 'خطة توزيع الطرود الغذائية', 'summary' => 'خطة لتوزيع الطرود على نقاط محددة.'], 'en' => ['title' => 'Food parcel distribution plan', 'summary' => 'A plan for parcel distribution at set points.']],
        ['resource' => 'distribution_plans', 'index' => 2, 'ar' => ['title' => 'خطة توزيع طرود المأوى', 'summary' => 'خطة لجرد وتوزيع طرود المأوى.'], 'en' => ['title' => 'Shelter kit distribution plan', 'summary' => 'A plan for counting and distributing shelter kits.']],
        ['resource' => 'warehouses', 'index' => 1, 'ar' => ['title' => 'المخزن الرئيسي', 'summary' => 'مخزن للمواد الغذائية ومواد المأوى.'], 'en' => ['title' => 'Main warehouse', 'summary' => 'A warehouse for food and shelter items.']],
        ['resource' => 'warehouses', 'index' => 2, 'ar' => ['title' => 'المخزن الاحتياطي', 'summary' => 'مخزن مغلق مؤقتا لإعادة الترتيب.'], 'en' => ['title' => 'Reserve warehouse', 'summary' => 'A warehouse temporarily closed for reorganisation.']],
        ['resource' => 'volunteer_teams', 'index' => 1, 'ar' => ['title' => 'فريق التوزيع الميداني', 'summary' => 'فريق لتنسيق الجولات الميدانية.'], 'en' => ['title' => 'Field distribution team', 'summary' => 'A team coordinating field distribution rounds.']],
        ['resource' => 'volunteer_teams', 'index' => 2, 'ar' => ['title' => 'فريق اللوجستيات', 'summary' => 'فريق للجرد والتخزين.'], 'en' => ['title' => 'Logistics team', 'summary' => 'A team responsible for stock counting and storage.']],
        ['resource' => 'committees', 'index' => 1, 'ar' => ['title' => 'لجنة جودة التوزيع', 'summary' => 'لجنة لمراجعة جودة الجولات.'], 'en' => ['title' => 'Distribution quality committee', 'summary' => 'A committee reviewing distribution round quality.']],
        ['resource' => 'committees', 'index' => 2, 'ar' => ['title' => 'لجنة التنسيق الميداني', 'summary' => 'لجنة لتنسيق الجداول والمواقع.'], 'en' => ['title' => 'Field coordination committee', 'summary' => 'A committee coordinating schedules and sites.']],
    ];

    /** @var array<string, list<list<string|null>>> */
    private const TRANSITION_CHAINS = [
        'interventions' => [
            [null, 'draft', 'active'],
            [null, 'draft', 'active'],
            [null, 'draft', 'on_hold'],
            [null, 'draft', 'active', 'completed'],
            [null, 'draft', 'archived'],
            [null, 'draft', 'active', 'cancelled'],
        ],
        'distribution_plans' => [
            [null, 'planned', 'scheduled'],
            [null, 'planned', 'scheduled', 'in_progress'],
            [null, 'planned', 'scheduled', 'in_progress', 'completed', 'reconciled'],
            [null, 'planned', 'cancelled'],
        ],
        'warehouses' => [
            [null, 'active'],
            [null, 'active', 'inactive'],
            [null, 'inactive', 'active'],
        ],
        'volunteer_teams' => [
            [null, 'proposed', 'active'],
            [null, 'proposed', 'active'],
            [null, 'proposed', 'active', 'inactive'],
            [null, 'proposed', 'archived'],
        ],
        'committees' => [
            [null, 'proposed', 'established'],
            [null, 'proposed', 'established', 'active'],
            [null, 'proposed', 'established', 'inactive'],
        ],
        'evaluations' => [
            [null, 'planned', 'in_progress'],
            [null, 'planned', 'in_progress', 'completed'],
            [null, 'planned', 'archived'],
            [null, 'planned'],
        ],
        'partners' => [
            [null, 'potential', 'contacted'],
            [null, 'potential', 'contacted'],
        ],
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        $userIds = $this->userIds($manifest);
        $mediaIds = $this->mediaIds($manifest);
        $programIds = $this->relatedIds($manifest, 'program', 'PRG', 'programs', 6);
        $projectIds = $this->relatedIds($manifest, 'project', 'PRJ', 'projects', 8);

        $locationIds = $this->seedLocations($manifest);
        $inventoryItemIds = $this->seedInventoryItems($manifest);
        $interventionIds = $this->seedInterventions($manifest, $programIds, $projectIds, $locationIds, $userIds);
        $activityIds = $this->seedActivities($manifest, $projectIds, $interventionIds, $locationIds, $userIds);
        $distribution = $this->seedDistributions($manifest, $projectIds, $interventionIds, $locationIds, $userIds, $inventoryItemIds, $mediaIds);
        $warehouseIds = $this->seedWarehousesAndInventory($manifest, $locationIds, $userIds, $inventoryItemIds, $mediaIds);

        $volunteer = $this->seedVolunteers($manifest, $locationIds, $userIds, $activityIds, $distribution['roundIds'], $mediaIds);
        $committeeIds = $this->seedCommittees($manifest, $locationIds, $volunteer['volunteerIds'], $mediaIds);
        $partnerIds = $this->seedPartners($manifest, $locationIds, $projectIds, $mediaIds);
        $grant = $this->seedGrants($manifest, $programIds, $projectIds, $partnerIds, $userIds, $mediaIds);
        $meal = $this->seedMeal($manifest, $programIds, $projectIds, $interventionIds, $userIds, $mediaIds);
        $assessment = $this->seedAssessments($manifest, $locationIds, $programIds, $projectIds, $mediaIds);

        $this->seedReports($manifest, $programIds, $projectIds, $locationIds, $userIds);

        $this->seedTranslations($manifest, [
            'inventory_items' => array_slice($inventoryItemIds, 0, 2),
            'locations' => array_slice($locationIds, 0, 4),
            'interventions' => array_slice($interventionIds, 0, 4),
            'activities' => array_slice($activityIds, 0, 2),
            'distribution_plans' => array_slice($distribution['planIds'], 0, 2),
            'warehouses' => array_slice($warehouseIds, 0, 2),
            'volunteer_teams' => array_slice($volunteer['teamIds'], 0, 2),
            'committees' => array_slice($committeeIds, 0, 2),
        ]);

        $this->seedTransitions($manifest, [
            'interventions' => $interventionIds,
            'distribution_plans' => $distribution['planIds'],
            'warehouses' => $warehouseIds,
            'volunteer_teams' => $volunteer['teamIds'],
            'committees' => $committeeIds,
            'evaluations' => $meal['evaluationIds'],
            'partners' => $partnerIds,
        ], $userIds, $mediaIds);

        $this->seedDocumentLinks($manifest, [
            'interventions' => array_slice($interventionIds, 0, 4),
            'activities' => array_slice($activityIds, 0, 4),
            'partners' => array_slice($partnerIds, 0, 4),
            'grant_proposals' => array_slice($grant['proposalIds'], 0, 4),
            'evaluations' => array_slice($meal['evaluationIds'], 0, 2),
            'assessments' => array_slice($assessment['assessmentIds'], 0, 2),
        ], $mediaIds, $userIds);
    }

    /** @return list<int> */
    private function seedLocations(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::LOCATIONS as $index => $definition) {
            $parentId = $definition['parent'] === null ? null : $ids[$definition['parent'] - 1];

            $id = $manifest->ensureRow(
                'locations',
                'locations',
                $definition['code'],
                [
                    'parent_id' => $parentId,
                    'code' => $definition['code'],
                    'location_type' => $definition['type'],
                    'status' => $definition['status'],
                ] + $this->stampsAt($index + 1),
                ['code' => $definition['code']],
            );

            $ids[] = (int) $id;
        }

        return $ids;
    }

    /** @return list<int> */
    private function seedInventoryItems(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::INVENTORY_ITEMS as $index => $definition) {
            $id = $manifest->ensureRow(
                'inventory_items',
                'inventory_items',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'category' => $definition['category'],
                    'unit' => $definition['unit'],
                    'sku' => $definition['sku'],
                    'min_stock_threshold' => $definition['threshold'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(20 + $index),
                ['code' => $definition['code']],
            );

            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function seedInterventions(
        DemoSeedManifest $manifest,
        array $programIds,
        array $projectIds,
        array $locationIds,
        array $userIds,
    ): array {
        $ids = [];

        foreach (self::INTERVENTIONS as $index => $definition) {
            $id = $manifest->ensureRow(
                'interventions',
                'interventions',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'program_id' => $this->pick($programIds, $definition['program'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'category' => $definition['category'],
                    'planned_start' => $definition['planned_start'],
                    'planned_end' => $definition['planned_end'],
                    'actual_start' => $definition['actual_start'],
                    'actual_end' => $definition['actual_end'],
                    'planned_reach' => $definition['planned_reach'],
                    'verified_reach' => $definition['verified_reach'],
                    'responsible_user_id' => $this->pick($userIds, $index),
                    'status' => $definition['status'],
                ] + $this->stampsAt(40 + $index),
                ['code' => $definition['code']],
            );

            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $projectIds
     * @param  list<int>  $interventionIds
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function seedActivities(
        DemoSeedManifest $manifest,
        array $projectIds,
        array $interventionIds,
        array $locationIds,
        array $userIds,
    ): array {
        $ids = [];

        foreach (self::ACTIVITIES as $index => $definition) {
            $id = $manifest->ensureRow(
                'activities',
                'activities',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'intervention_id' => $this->pick($interventionIds, $definition['intervention'] - 1),
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'activity_type' => $definition['activity_type'],
                    'planned_start' => $definition['planned_start'],
                    'planned_end' => $definition['planned_end'],
                    'actual_start' => $definition['actual_start'],
                    'actual_end' => $definition['actual_end'],
                    'progress_percent' => $definition['progress'],
                    'owner_id' => $this->pick($userIds, $index + 1),
                    'status' => $definition['status'],
                ] + $this->stampsAt(60 + $index),
                ['code' => $definition['code']],
            );

            $ids[] = (int) $id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $projectIds
     * @param  list<int>  $interventionIds
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     * @param  list<int>  $inventoryItemIds
     * @param  list<int>  $mediaIds
     * @return array{planIds: list<int>, roundIds: list<int>, recordIds: list<int>}
     */
    private function seedDistributions(
        DemoSeedManifest $manifest,
        array $projectIds,
        array $interventionIds,
        array $locationIds,
        array $userIds,
        array $inventoryItemIds,
        array $mediaIds,
    ): array {
        $planIds = [];

        foreach (self::DISTRIBUTION_PLANS as $index => $definition) {
            $id = $manifest->ensureRow(
                'distribution_plans',
                'distribution_plans',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'intervention_id' => $this->pick($interventionIds, $definition['intervention'] - 1),
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'assistance_type' => $definition['assistance_type'],
                    'planned_date' => $definition['planned_date'],
                    'responsible_user_id' => $this->pick($userIds, $definition['responsible'] - 1),
                    'status' => $definition['status'],
                ] + $this->stampsAt(80 + $index),
                ['code' => $definition['code']],
            );

            $planIds[] = (int) $id;
        }

        $roundIds = [];

        foreach (self::DISTRIBUTION_ROUNDS as $index => $definition) {
            $id = $manifest->ensureRow(
                'distribution_rounds',
                'distribution_rounds',
                $definition['code'],
                [
                    'plan_id' => $planIds[$definition['plan'] - 1],
                    'code' => $definition['code'],
                    'scheduled_at' => $definition['scheduled_at'],
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'responsible_user_id' => $this->pick($userIds, $definition['responsible'] - 1),
                    'status' => $definition['status'],
                ] + $this->stampsAt(100 + $index),
                ['code' => $definition['code']],
            );

            $roundIds[] = (int) $id;
        }

        $recordIds = [];

        for ($index = 1; $index <= 16; $index++) {
            $code = sprintf('DREC-%03d', $index);
            $status = self::DISTRIBUTION_RECORD_STATUSES[($index - 1) % 4];
            $plannedQuantity = 100 + ($index * 10);
            $recordedQuantity = in_array($status, ['recorded', 'verified', 'reconciled'], true)
                ? (string) ($plannedQuantity - ($index % 3) * 2)
                : null;
            $verifiedQuantity = in_array($status, ['verified', 'reconciled'], true)
                ? (string) ($recordedQuantity ?? (string) $plannedQuantity)
                : null;

            $id = $manifest->ensureRow(
                'distribution_records',
                'distribution_records',
                $code,
                [
                    'round_id' => $roundIds[intdiv($index - 1, 2)],
                    'code' => $code,
                    'inventory_item_id' => $this->pick($inventoryItemIds, $index - 1),
                    'planned_quantity' => (string) $plannedQuantity,
                    'recorded_quantity' => $recordedQuantity,
                    'verified_quantity' => $verifiedQuantity,
                    'unit' => self::INVENTORY_ITEMS[($index - 1) % 10]['unit'],
                    'evidence_media_id' => $index % 4 === 0 ? $this->pick($mediaIds, $index) : null,
                    'reconciliation_notes' => $status === 'reconciled' ? 'تمت مطابقة الكميات مع سجل الجرد.' : null,
                    'status' => $status,
                ] + $this->stampsAt(130 + $index),
                ['code' => $code],
            );

            $recordIds[] = (int) $id;
        }

        return ['planIds' => $planIds, 'roundIds' => $roundIds, 'recordIds' => $recordIds];
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     * @param  list<int>  $inventoryItemIds
     * @param  list<int>  $mediaIds
     * @return list<int>
     */
    private function seedWarehousesAndInventory(
        DemoSeedManifest $manifest,
        array $locationIds,
        array $userIds,
        array $inventoryItemIds,
        array $mediaIds,
    ): array {
        $warehouseIds = [];

        foreach (self::WAREHOUSES as $index => $definition) {
            $id = $manifest->ensureRow(
                'warehouses',
                'warehouses',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'responsible_user_id' => $this->pick($userIds, $definition['responsible'] - 1),
                    'capacity_note' => $definition['capacity_note'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(160 + $index),
                ['code' => $definition['code']],
            );

            $warehouseIds[] = (int) $id;
        }

        /** @var list<array{warehouse_id: int, inventory_item_id: int, base: int, receipt: int, final: int, code: string}> $combos */
        $combos = [];

        foreach ($warehouseIds as $warehouseIndex => $warehouseId) {
            foreach ($inventoryItemIds as $itemIndex => $inventoryItemId) {
                $index = ($warehouseIndex * 10) + $itemIndex;
                $base = 100 + ($index * 7);
                $receipt = $index % 3 === 0 ? 5 + ($index % 5) * 3 : 0;
                $code = sprintf('WH-%03d:INV-%03d', $warehouseIndex + 1, $itemIndex + 1);

                $manifest->ensureRow(
                    'inventory_balances',
                    'inventory_balances',
                    $code,
                    [
                        'warehouse_id' => $warehouseId,
                        'inventory_item_id' => $inventoryItemId,
                        'quantity' => (string) ($base + $receipt),
                        'reserved_quantity' => (string) (($index % 4) * 3),
                        'damaged_quantity' => (string) ($index % 3),
                        'expired_quantity' => (string) ($index % 2),
                        'updated_at' => $this->at(700 + $index),
                    ],
                    ['warehouse_id' => $warehouseId, 'inventory_item_id' => $inventoryItemId],
                );

                $combos[] = [
                    'warehouse_id' => $warehouseId,
                    'inventory_item_id' => $inventoryItemId,
                    'base' => $base,
                    'receipt' => $receipt,
                    'final' => $base + $receipt,
                    'code' => $code,
                ];
            }
        }

        $sequence = 0;

        foreach ($combos as $combo) {
            $sequence++;
            $createdAt = $this->at(600 + $sequence);

            $this->insertOnce(
                $manifest,
                'inventory_movements',
                ['idempotency_key' => 'opening-'.$combo['code']],
                [
                    'warehouse_id' => $combo['warehouse_id'],
                    'destination_warehouse_id' => null,
                    'inventory_item_id' => $combo['inventory_item_id'],
                    'movement_type' => 'opening',
                    'direction' => 'in',
                    'quantity' => (string) $combo['base'],
                    'before_quantity' => '0.000',
                    'after_quantity' => (string) $combo['base'],
                    'source_type' => 'inventory_balance',
                    'source_id' => null,
                    'source_reference' => $combo['code'],
                    'reason' => 'رصيد افتتاحي مسجل أثناء جرد المخزون.',
                    'actor_id' => $this->pick($userIds, $sequence),
                    'reversal_of' => null,
                    'idempotency_key' => 'opening-'.$combo['code'],
                    'occurred_at' => $createdAt,
                    'created_at' => $createdAt,
                ],
            );
        }

        foreach ($combos as $combo) {
            if ($combo['receipt'] === 0) {
                continue;
            }

            $sequence++;
            $createdAt = $this->at(600 + $sequence);

            $this->insertOnce(
                $manifest,
                'inventory_movements',
                ['idempotency_key' => 'receipt-'.$combo['code']],
                [
                    'warehouse_id' => $combo['warehouse_id'],
                    'destination_warehouse_id' => null,
                    'inventory_item_id' => $combo['inventory_item_id'],
                    'movement_type' => 'receipt',
                    'direction' => 'in',
                    'quantity' => (string) $combo['receipt'],
                    'before_quantity' => (string) $combo['base'],
                    'after_quantity' => (string) $combo['final'],
                    'source_type' => 'supplier_delivery',
                    'source_id' => null,
                    'source_reference' => 'DEL-'.$combo['code'],
                    'reason' => 'إيصال دفعة إضافية واردة إلى المخزن.',
                    'actor_id' => $this->pick($userIds, $sequence),
                    'reversal_of' => null,
                    'idempotency_key' => 'receipt-'.$combo['code'],
                    'occurred_at' => $createdAt,
                    'created_at' => $createdAt,
                ],
            );
        }

        return $warehouseIds;
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     * @param  list<int>  $activityIds
     * @param  list<int>  $roundIds
     * @param  list<int>  $mediaIds
     * @return array{volunteerIds: list<int>, teamIds: list<int>}
     */
    private function seedVolunteers(
        DemoSeedManifest $manifest,
        array $locationIds,
        array $userIds,
        array $activityIds,
        array $roundIds,
        array $mediaIds,
    ): array {
        $volunteerIds = [];

        foreach (self::VOLUNTEERS as $index => $definition) {
            $id = $manifest->ensureRow(
                'volunteers',
                'volunteers',
                $definition['reference'],
                [
                    'internal_reference' => $definition['reference'],
                    'linked_user_id' => null,
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'skills' => $definition['skills'],
                    'availability' => $definition['availability'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(200 + $index),
                ['internal_reference' => $definition['reference']],
            );

            $volunteerIds[] = (int) $id;
        }

        $teamIds = [];

        foreach (self::VOLUNTEER_TEAMS as $index => $definition) {
            $id = $manifest->ensureRow(
                'volunteer_teams',
                'volunteer_teams',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'team_type' => $definition['team_type'],
                    'leader_volunteer_id' => $this->pick($volunteerIds, $definition['leader'] - 1),
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'skills' => $definition['skills'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(220 + $index),
                ['code' => $definition['code']],
            );

            $teamIds[] = (int) $id;
        }

        foreach ($teamIds as $teamIndex => $teamId) {
            for ($memberIndex = 0; $memberIndex < 3; $memberIndex++) {
                $volunteerId = $this->pick($volunteerIds, ($teamIndex * 3) + $memberIndex);

                if ($volunteerId === null) {
                    continue;
                }

                $manifest->ensurePivot(
                    'volunteer_team_members',
                    ['team_id' => $teamId, 'volunteer_id' => $volunteerId],
                    [
                        'member_role' => self::TEAM_MEMBER_ROLES[$memberIndex],
                        'status' => $teamIndex === 3 && $memberIndex === 2 ? 'inactive' : 'active',
                        'joined_at' => sprintf('2026-09-%02d', 5 + ($teamIndex * 3) + $memberIndex),
                    ],
                );
            }
        }

        for ($index = 1; $index <= 10; $index++) {
            $code = sprintf('VAS-%03d', $index);

            $manifest->ensureRow(
                'volunteer_assignments',
                'volunteer_assignments',
                $code,
                [
                    'team_id' => $this->pick($teamIds, $index - 1),
                    'volunteer_id' => $this->pick($volunteerIds, $index - 1),
                    'activity_id' => $this->pick($activityIds, $index - 1),
                    'distribution_round_id' => $this->pick($roundIds, $index - 1),
                    'scheduled_at' => $this->at(240 + $index),
                    'hours' => number_format(2 + ($index % 5) + 0.5, 2, '.', ''),
                    'status' => self::ASSIGNMENT_STATUSES[($index - 1) % 4],
                ] + $this->stampsAt(240 + $index),
                [
                    'team_id' => $this->pick($teamIds, $index - 1),
                    'volunteer_id' => $this->pick($volunteerIds, $index - 1),
                    'activity_id' => $this->pick($activityIds, $index - 1),
                    'distribution_round_id' => $this->pick($roundIds, $index - 1),
                    'scheduled_at' => $this->at(240 + $index),
                ],
            );
        }

        $trainingIds = [];

        foreach (self::VOLUNTEER_TRAININGS as $index => $definition) {
            $id = $manifest->ensureRow(
                'volunteer_trainings',
                'volunteer_trainings',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'provider' => $definition['provider'],
                    'training_date' => $definition['training_date'],
                    'hours' => $definition['hours'],
                    'evidence_media_id' => $definition['status'] === 'completed' ? $this->pick($mediaIds, $index) : null,
                    'status' => $definition['status'],
                ] + $this->stampsAt(260 + $index),
                ['code' => $definition['code']],
            );

            $trainingIds[] = (int) $id;
        }

        for ($index = 1; $index <= 8; $index++) {
            $trainingIndex = ($index - 1) % 4;
            $trainingStatus = self::VOLUNTEER_TRAININGS[$trainingIndex]['status'];
            $volunteerId = $this->pick($volunteerIds, $index - 1);
            $trainingId = $this->pick($trainingIds, $trainingIndex);

            if ($volunteerId === null || $trainingId === null) {
                continue;
            }

            $manifest->ensurePivot(
                'volunteer_training',
                ['volunteer_id' => $volunteerId, 'training_id' => $trainingId],
                [
                    'completion_date' => $trainingStatus === 'completed' ? self::VOLUNTEER_TRAININGS[$trainingIndex]['training_date'] : null,
                    'status' => $trainingStatus,
                ],
            );
        }

        return ['volunteerIds' => $volunteerIds, 'teamIds' => $teamIds];
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $volunteerIds
     * @param  list<int>  $mediaIds
     * @return list<int>
     */
    private function seedCommittees(
        DemoSeedManifest $manifest,
        array $locationIds,
        array $volunteerIds,
        array $mediaIds,
    ): array {
        $committeeIds = [];

        foreach (self::COMMITTEES as $index => $definition) {
            $id = $manifest->ensureRow(
                'committees',
                'committees',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'committee_type' => $definition['committee_type'],
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'status' => $definition['status'],
                ] + $this->stampsAt(300 + $index),
                ['code' => $definition['code']],
            );

            $committeeIds[] = (int) $id;
        }

        foreach ($committeeIds as $committeeIndex => $committeeId) {
            for ($memberIndex = 0; $memberIndex < 3; $memberIndex++) {
                $number = ($committeeIndex * 3) + $memberIndex + 1;

                $manifest->ensurePivot(
                    'committee_members',
                    [
                        'committee_id' => $committeeId,
                        'internal_reference' => sprintf('CM-REF-%03d', $number),
                    ],
                    [
                        'volunteer_id' => $number <= 6 ? $this->pick($volunteerIds, $number - 1) : null,
                        'member_role' => self::COMMITTEE_MEMBER_ROLES[$memberIndex],
                        'status' => $number === 9 ? 'inactive' : 'active',
                        'joined_at' => sprintf('2026-09-%02d', 2 + $number),
                    ],
                );
            }
        }

        foreach (self::COMMITTEE_MEETINGS as $index => $definition) {
            $manifest->ensureRow(
                'committee_meetings',
                'committee_meetings',
                $definition['code'],
                [
                    'committee_id' => $committeeIds[$definition['committee'] - 1],
                    'code' => $definition['code'],
                    'meeting_date' => $definition['meeting_date'],
                    'minutes' => $definition['minutes'],
                    'decisions' => $definition['decisions'],
                    'evidence_media_id' => $definition['status'] === 'held' ? $this->pick($mediaIds, $index) : null,
                    'status' => $definition['status'],
                ] + $this->stampsAt(320 + $index),
                ['code' => $definition['code']],
            );
        }

        foreach (self::COMMUNITY_FEEDBACK as $index => $definition) {
            $reference = sprintf('FB-%03d', $index + 1);

            $manifest->ensureRow(
                'community_feedback',
                'community_feedback',
                $reference,
                [
                    'committee_id' => $this->pick($committeeIds, $index),
                    'category' => $definition['category'],
                    'summary' => $definition['summary'],
                    'source_reference' => $reference,
                    'status' => $definition['status'],
                ] + $this->stampsAt(340 + $index),
                ['source_reference' => $reference],
            );
        }

        return $committeeIds;
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $mediaIds
     * @return list<int>
     */
    private function seedPartners(
        DemoSeedManifest $manifest,
        array $locationIds,
        array $projectIds,
        array $mediaIds,
    ): array {
        $partnerIds = [];

        foreach (self::PARTNERS as $index => $definition) {
            $id = $manifest->ensureRow(
                'partners',
                'partners',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'partner_type' => $definition['partner_type'],
                    'organization_type' => $definition['organization_type'],
                    'country' => 'Jordan',
                    'location_id' => $this->pick($locationIds, $index),
                    'website' => sprintf('https://partner-%03d.example.test', $index + 1),
                    'public_contact' => null,
                    'internal_notes' => $definition['internal_notes'],
                    'strategic_alignment' => 'مرتبط بمحاور التشغيل الميداني وتنسيق البرامج.',
                    'relationship_status' => $definition['relationship_status'],
                ] + $this->stampsAt(360 + $index),
                ['code' => $definition['code']],
            );

            $partnerIds[] = (int) $id;
        }

        for ($index = 1; $index <= 10; $index++) {
            $manifest->ensurePivot(
                'partner_project',
                [
                    'partner_id' => $this->pick($partnerIds, $index - 1),
                    'project_id' => $this->pick($projectIds, $index - 1),
                ],
                ['relationship_type' => self::PARTNER_PROJECT_RELATIONSHIPS[($index - 1) % 3]],
            );
        }

        for ($index = 1; $index <= 6; $index++) {
            $reference = sprintf('PAGR-%03d', $index);

            $manifest->ensureRow(
                'partner_agreements',
                'partner_agreements',
                $reference,
                [
                    'partner_id' => $this->pick($partnerIds, $index - 1),
                    'reference' => $reference,
                    'start_date' => date('Y-m-d', (int) strtotime('2026-09-01 +'.($index - 1).' months')),
                    'end_date' => date('Y-m-d', (int) strtotime('2026-09-01 +'.$index.' months -1 day')),
                    'document_media_id' => $this->pick($mediaIds, $index + 1),
                    'status' => self::PARTNER_AGREEMENT_STATUSES[$index - 1],
                ] + $this->stampsAt(380 + $index),
                ['reference' => $reference],
            );
        }

        for ($index = 1; $index <= 12; $index++) {
            $reference = sprintf('PC-%03d', $index);

            $manifest->ensureRow(
                'partner_contacts',
                'partner_contacts',
                $reference,
                [
                    'partner_id' => $this->pick($partnerIds, $index - 1),
                    'internal_reference' => $reference,
                    'name' => 'Internal contact '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'email' => null,
                    'phone' => null,
                    'role' => self::PARTNER_CONTACT_ROLES[($index - 1) % 3],
                    'status' => $index % 4 === 0 ? 'inactive' : 'active',
                ] + $this->stampsAt(400 + $index),
                ['internal_reference' => $reference],
            );
        }

        return $partnerIds;
    }

    /**
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $partnerIds
     * @param  list<int>  $userIds
     * @param  list<int>  $mediaIds
     * @return array{opportunityIds: list<int>, proposalIds: list<int>, awardIds: list<int>}
     */
    private function seedGrants(
        DemoSeedManifest $manifest,
        array $programIds,
        array $projectIds,
        array $partnerIds,
        array $userIds,
        array $mediaIds,
    ): array {
        $opportunityIds = [];

        foreach (self::GRANT_OPPORTUNITIES as $index => $definition) {
            $id = $manifest->ensureRow(
                'grant_opportunities',
                'grant_opportunities',
                $definition['reference'],
                [
                    'reference' => $definition['reference'],
                    'funder_partner_id' => $this->pick($partnerIds, $definition['partner'] - 1),
                    'category' => $definition['category'],
                    'geographic_eligibility' => 'داخل نطاق العمليات المعتمد فقط.',
                    'sector_eligibility' => 'البرامج والمشاريع المسجلة في الخطة التشغيلية.',
                    'min_amount' => $definition['min_amount'],
                    'max_amount' => $definition['max_amount'],
                    'currency' => 'USD',
                    'opens_at' => $definition['opens_at'],
                    'closes_at' => $definition['closes_at'],
                    'source_url' => sprintf('https://grants.example.test/%03d', $index + 1),
                    'owner_id' => $this->pick($userIds, $index),
                    'status' => $definition['status'],
                ] + $this->stampsAt(430 + $index),
                ['reference' => $definition['reference']],
            );

            $opportunityIds[] = (int) $id;
        }

        $proposalIds = [];

        foreach (self::GRANT_PROPOSALS as $index => $definition) {
            $id = $manifest->ensureRow(
                'grant_proposals',
                'grant_proposals',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'opportunity_id' => $this->pick($opportunityIds, $definition['opportunity'] - 1),
                    'program_id' => $this->pick($programIds, $definition['program'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'budget_amount' => $definition['budget_amount'],
                    'currency' => 'USD',
                    'deadline' => $definition['deadline'],
                    'submitted_at' => in_array($definition['status'], ['submitted', 'awarded'], true) ? '2026-10-05' : null,
                    'submission_evidence_media_id' => in_array($definition['status'], ['submitted', 'awarded'], true) ? $this->pick($mediaIds, $index + 3) : null,
                    'responsible_user_id' => $this->pick($userIds, $index + 1),
                    'status' => $definition['status'],
                ] + $this->stampsAt(450 + $index),
                ['code' => $definition['code']],
            );

            $proposalIds[] = (int) $id;
        }

        $awardIds = [];

        foreach (self::GRANT_AWARDS as $index => $definition) {
            $id = $manifest->ensureRow(
                'grant_awards',
                'grant_awards',
                $definition['reference'],
                [
                    'reference' => $definition['reference'],
                    'donor_partner_id' => $this->pick($partnerIds, $definition['donor'] - 1),
                    'proposal_id' => $this->pick($proposalIds, $definition['proposal'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'amount' => $definition['amount'],
                    'currency' => 'USD',
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'payment_schedule_note' => 'دفعات ربع سنوية حسب الخطة المالية المعتمدة.',
                    'agreement_media_id' => $this->pick($mediaIds, $index + 4),
                    'evidence_media_id' => $definition['status'] === 'awarded' ? $this->pick($mediaIds, $index + 5) : null,
                    'status' => $definition['status'],
                ] + $this->stampsAt(470 + $index),
                ['reference' => $definition['reference']],
            );

            $awardIds[] = (int) $id;
        }

        return ['opportunityIds' => $opportunityIds, 'proposalIds' => $proposalIds, 'awardIds' => $awardIds];
    }

    /**
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $interventionIds
     * @param  list<int>  $userIds
     * @param  list<int>  $mediaIds
     * @return array{frameworkIds: list<int>, periodIds: list<int>, indicatorIds: list<int>, evaluationIds: list<int>}
     */
    private function seedMeal(
        DemoSeedManifest $manifest,
        array $programIds,
        array $projectIds,
        array $interventionIds,
        array $userIds,
        array $mediaIds,
    ): array {
        $frameworkIds = [];

        foreach (self::RESULTS_FRAMEWORKS as $index => $definition) {
            $id = $manifest->ensureRow(
                'results_frameworks',
                'results_frameworks',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'program_id' => $this->pick($programIds, $definition['program'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'theory_of_change' => $definition['theory_of_change'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(500 + $index),
                ['code' => $definition['code']],
            );

            $frameworkIds[] = (int) $id;
        }

        $periodIds = [];

        foreach (self::REPORTING_PERIODS as $index => $definition) {
            $id = $manifest->ensureRow(
                'reporting_periods',
                'reporting_periods',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(520 + $index),
                ['code' => $definition['code']],
            );

            $periodIds[] = (int) $id;
        }

        $indicatorIds = [];

        foreach (self::INDICATORS as $index => $definition) {
            $id = $manifest->ensureRow(
                'indicators',
                'indicators',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'framework_id' => $this->pick($frameworkIds, $index % 4),
                    'program_id' => $this->pick($programIds, $index % 6),
                    'project_id' => $this->pick($projectIds, $index % 10),
                    'intervention_id' => $index % 3 === 0 ? $this->pick($interventionIds, $index % 6) : null,
                    'indicator_type' => $definition['indicator_type'],
                    'unit' => $definition['unit'],
                    'baseline_value' => $definition['baseline'],
                    'target_value' => $definition['target'],
                    'calculation_rule' => $definition['calculation_rule'],
                    'disaggregation_enabled' => false,
                    'status' => $definition['status'],
                ] + $this->stampsAt(540 + $index),
                ['code' => $definition['code']],
            );

            $indicatorIds[] = (int) $id;
        }

        for ($index = 1; $index <= 24; $index++) {
            $indicatorIndex = intdiv($index - 1, 2);
            $periodIndex = ($index - 1) % 4;
            $status = self::MEASUREMENT_STATUSES[($index - 1) % 6];
            $dataSource = sprintf('Monitoring dataset %03d', $index);
            $submitted = $status !== 'draft';
            $verified = in_array($status, ['verified', 'reported'], true);
            $reportedValue = (string) (50 + ($index * 3)).'.0000';

            $manifest->ensureRow(
                'indicator_measurements',
                'indicator_measurements',
                sprintf('IND-%03d:RP-%03d:%d', $indicatorIndex + 1, $periodIndex + 1, ($index - 1) % 2 + 1),
                [
                    'indicator_id' => $this->pick($indicatorIds, $indicatorIndex),
                    'period_id' => $this->pick($periodIds, $periodIndex),
                    'reported_value' => $reportedValue,
                    'verified_value' => $verified ? (string) (48 + ($index * 3)).'.0000' : null,
                    'approved_value' => $status === 'reported' ? (string) (47 + ($index * 3)).'.0000' : null,
                    'data_source' => $dataSource,
                    'evidence_media_id' => $index % 3 === 0 ? $this->pick($mediaIds, $index) : null,
                    'verifier_id' => $submitted ? $this->pick($userIds, $index) : null,
                    'verification_notes' => $verified ? 'تم التحقق وفق إجراءات المراجعة المعتمدة.' : null,
                    'submitted_at' => $submitted ? sprintf('2026-10-%02d', 2 + ($index % 20)) : null,
                    'verified_at' => $verified ? sprintf('2026-10-%02d', 5 + ($index % 20)) : null,
                    'status' => $status,
                ] + $this->stampsAt(570 + $index),
                [
                    'indicator_id' => $this->pick($indicatorIds, $indicatorIndex),
                    'period_id' => $this->pick($periodIds, $periodIndex),
                    'data_source' => $dataSource,
                ],
            );
        }

        $evaluationIds = [];

        foreach (self::EVALUATIONS as $index => $definition) {
            $id = $manifest->ensureRow(
                'evaluations',
                'evaluations',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'evaluation_type' => $definition['evaluation_type'],
                    'evidence_media_id' => $this->pick($mediaIds, $index + 7),
                    'status' => $definition['status'],
                ] + $this->stampsAt(600 + $index),
                ['code' => $definition['code']],
            );

            $evaluationIds[] = (int) $id;
        }

        for ($index = 1; $index <= 6; $index++) {
            $code = sprintf('CA-%03d', $index);

            $manifest->ensureRow(
                'corrective_actions',
                'corrective_actions',
                $code,
                [
                    'code' => $code,
                    'evaluation_id' => $this->pick($evaluationIds, $index - 1),
                    'owner_id' => $this->pick($userIds, $index + 2),
                    'due_date' => sprintf('2026-11-%02d', 5 + $index),
                    'status' => ['open', 'in_progress', 'completed', 'cancelled'][($index - 1) % 4],
                ] + $this->stampsAt(620 + $index),
                ['code' => $code],
            );
        }

        return [
            'frameworkIds' => $frameworkIds,
            'periodIds' => $periodIds,
            'indicatorIds' => $indicatorIds,
            'evaluationIds' => $evaluationIds,
        ];
    }

    /**
     * @param  list<int>  $locationIds
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $mediaIds
     * @return array{assessmentIds: list<int>, instrumentIds: list<int>}
     */
    private function seedAssessments(
        DemoSeedManifest $manifest,
        array $locationIds,
        array $programIds,
        array $projectIds,
        array $mediaIds,
    ): array {
        $pillarId = $this->firstPillarId();
        $assessmentIds = [];

        foreach (self::ASSESSMENTS as $index => $definition) {
            $id = $manifest->ensureRow(
                'assessments',
                'assessments',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'assessment_type' => $definition['assessment_type'],
                    'need_category' => $definition['need_category'],
                    'methodology_type' => $definition['methodology_type'],
                    'target_population_definition' => $definition['target_population_definition'],
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'program_id' => $this->pick($programIds, $definition['program'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'strategic_pillar_id' => $pillarId,
                    'evidence_media_id' => $this->pick($mediaIds, $index + 2),
                    'status' => $definition['status'],
                ] + $this->stampsAt(650 + $index),
                ['code' => $definition['code']],
            );

            $assessmentIds[] = (int) $id;
        }

        $instrumentIds = [];

        foreach (self::DATA_COLLECTION_INSTRUMENTS as $index => $definition) {
            $id = $manifest->ensureRow(
                'data_collection_instruments',
                'data_collection_instruments',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'instrument_type' => $definition['instrument_type'],
                    'version' => $definition['version'],
                    'sensitive_data' => false,
                    'status' => $definition['status'],
                ] + $this->stampsAt(670 + $index),
                ['code' => $definition['code']],
            );

            $instrumentIds[] = (int) $id;
        }

        for ($index = 1; $index <= 8; $index++) {
            $manifest->ensurePivot(
                'assessment_instrument',
                [
                    'assessment_id' => $this->pick($assessmentIds, ($index - 1) % 4),
                    'instrument_id' => $this->pick($instrumentIds, ($index - 1) % 6),
                ],
            );
        }

        return ['assessmentIds' => $assessmentIds, 'instrumentIds' => $instrumentIds];
    }

    /**
     * @param  list<int>  $programIds
     * @param  list<int>  $projectIds
     * @param  list<int>  $locationIds
     * @param  list<int>  $userIds
     */
    private function seedReports(
        DemoSeedManifest $manifest,
        array $programIds,
        array $projectIds,
        array $locationIds,
        array $userIds,
    ): void {
        foreach (self::OPERATIONAL_REPORTS as $index => $definition) {
            $manifest->ensureRow(
                'operational_reports',
                'operational_reports',
                $definition['code'],
                [
                    'code' => $definition['code'],
                    'category' => $definition['category'],
                    'date_from' => '2026-07-01',
                    'date_to' => '2026-09-30',
                    'program_id' => $this->pick($programIds, $definition['program'] - 1),
                    'project_id' => $this->pick($projectIds, $definition['project'] - 1),
                    'location_id' => $this->pick($locationIds, $definition['location'] - 1),
                    'status_filter' => $definition['status_filter'],
                    'generated_at' => $definition['status'] === 'draft' ? null : $this->at(700 + $index),
                    'generated_by' => $definition['status'] === 'draft' ? null : $this->pick($userIds, $index),
                    'summary' => $this->jsonEncode([
                        'category' => $definition['category'],
                        'record_count' => 10 + $index,
                        'scope' => 'operational_plan',
                    ]),
                    'format' => $definition['format'],
                    'status' => $definition['status'],
                ] + $this->stampsAt(700 + $index),
                ['code' => $definition['code']],
            );
        }
    }

    /**
     * @param  array<string, list<int>>  $owners
     */
    private function seedTranslations(DemoSeedManifest $manifest, array $owners): void
    {
        foreach (self::TRANSLATIONS as $index => $definition) {
            $ownerId = $owners[$definition['resource']][$definition['index'] - 1] ?? null;

            if ($ownerId === null) {
                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                $manifest->ensureRow(
                    'operational_translations',
                    'operational_translations',
                    $definition['resource'].':'.$ownerId.':'.$locale,
                    [
                        'resource' => $definition['resource'],
                        'owner_id' => $ownerId,
                        'locale' => $locale,
                    ] + $definition[$locale] + $this->stampsAt(720 + $index),
                    [
                        'resource' => $definition['resource'],
                        'owner_id' => $ownerId,
                        'locale' => $locale,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, list<int>>  $idsByResource
     * @param  list<int>  $userIds
     * @param  list<int>  $mediaIds
     */
    private function seedTransitions(
        DemoSeedManifest $manifest,
        array $idsByResource,
        array $userIds,
        array $mediaIds,
    ): void {
        $sequence = 0;

        foreach (self::TRANSITION_CHAINS as $resource => $chains) {
            $ids = $idsByResource[$resource] ?? [];

            foreach ($chains as $chainIndex => $chain) {
                $ownerId = $this->pick($ids, $chainIndex);

                if ($ownerId === null) {
                    continue;
                }

                for ($step = 1; $step < count($chain); $step++) {
                    $sequence++;
                    $createdAt = $this->at(800 + $sequence);
                    $fromStatus = $chain[$step - 1];
                    $toStatus = $chain[$step];

                    if ($toStatus === null) {
                        continue;
                    }

                    $this->insertOnce(
                        $manifest,
                        'domain_transitions',
                        [
                            'resource' => $resource,
                            'owner_id' => $ownerId,
                            'status_field' => 'status',
                            'from_status' => $fromStatus,
                            'to_status' => $toStatus,
                            'created_at' => $createdAt,
                        ],
                        [
                            'resource' => $resource,
                            'owner_id' => $ownerId,
                            'status_field' => 'status',
                            'from_status' => $fromStatus,
                            'to_status' => $toStatus,
                            'actor_id' => $this->pick($userIds, $sequence),
                            'reason' => $fromStatus === null
                                ? 'حالة أولية مسجلة عند إنشاء السجل.'
                                : 'تحديث الحالة بعد مراجعة الحالة التشغيلية.',
                            'evidence_media_id' => $sequence % 3 === 0 ? $this->pick($mediaIds, $sequence) : null,
                            'created_at' => $createdAt,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, list<int>>  $owners
     * @param  list<int>  $mediaIds
     * @param  list<int>  $userIds
     */
    private function seedDocumentLinks(
        DemoSeedManifest $manifest,
        array $owners,
        array $mediaIds,
        array $userIds,
    ): void {
        $index = 0;

        foreach ($owners as $resource => $ids) {
            foreach ($ids as $ownerId) {
                $index++;
                $mediaId = $this->pick($mediaIds, $index - 1);

                if ($mediaId === null) {
                    continue;
                }

                $usage = 'document-'.(intdiv($index - 1, max(1, count($mediaIds))) + 1);
                $createdAt = $this->at(900 + $index);

                $this->insertOnce(
                    $manifest,
                    'document_links',
                    [
                        'resource' => $resource,
                        'owner_id' => $ownerId,
                        'media_id' => $mediaId,
                        'usage' => $usage,
                    ],
                    [
                        'resource' => $resource,
                        'owner_id' => $ownerId,
                        'media_id' => $mediaId,
                        'usage' => $usage,
                        'uploaded_by' => $this->pick($userIds, $index),
                        'created_at' => $createdAt,
                    ],
                );
            }
        }
    }

    private function at(int $offsetMinutes): string
    {
        return date('Y-m-d H:i:s', (int) strtotime(DemoSeedManifest::BASE_TIME) + ($offsetMinutes * 60));
    }

    /** @return array{created_at: string, updated_at: string} */
    private function stampsAt(int $offsetMinutes): array
    {
        $stamp = $this->at($offsetMinutes);

        return ['created_at' => $stamp, 'updated_at' => $stamp];
    }

    /** @param list<int> $ids */
    private function pick(array $ids, int $index): ?int
    {
        if ($ids === []) {
            return null;
        }

        return $ids[$index % count($ids)];
    }

    private function keyedId(DemoSeedManifest $manifest, string $module, string $key): ?int
    {
        foreach (array_unique([$module, $module.'s']) as $prefix) {
            $value = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:'.$prefix.':'.$key);

            if ($value !== null && $value !== '' && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /** @return list<int> */
    private function userIds(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::USER_EMAILS as $email) {
            $id = $this->keyedId($manifest, 'users', $email);

            if ($id === null) {
                $value = DB::table('users')->where('email', $email)->value('id');
                $id = $value === null ? null : (int) $value;
            }

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @return list<int> */
    private function mediaIds(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::MEDIA_SLUGS as $slug) {
            $id = $this->keyedId($manifest, 'media', $slug);

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** @return list<int> */
    private function relatedIds(DemoSeedManifest $manifest, string $module, string $prefix, string $table, int $count): array
    {
        $ids = [];

        for ($number = 1; $number <= $count; $number++) {
            $code = sprintf('%s-%03d', $prefix, $number);
            $id = $this->keyedId($manifest, $module, $code);

            if ($id === null) {
                $value = DB::table($table)->where('code', $code)->value('id');
                $id = $value === null ? null : (int) $value;
            }

            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function firstPillarId(): ?int
    {
        $id = DB::table('strategic_pillars')->where('code', '01')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $values
     */
    private function insertOnce(DemoSeedManifest $manifest, string $table, array $match, array $values): void
    {
        $query = DB::table($table);

        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }

        if ($query->exists()) {
            return;
        }

        $id = DB::table($table)->insertGetId($values);
        $naturalKey = $table.':'.hash('sha256', $this->jsonEncode($match));
        $manifest->trackExistingRow($table, $naturalKey, 'id', $id);
    }

    /** @param array<string, mixed> $value */
    private function jsonEncode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
