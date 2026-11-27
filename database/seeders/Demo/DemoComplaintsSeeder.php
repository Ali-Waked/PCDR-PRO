<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Internally tracked public complaints and their workflow records.
 *
 * Case notes are written as natural professional copy. Complainant details,
 * ownership markers and cleanup tracking remain internal to the demo registry.
 */
final class DemoComplaintsSeeder extends Seeder
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
        'food-donations',
        'food-packing',
        'food-handover',
        'donation-sorting',
        'water-well',
        'handwashing',
        'community-garden',
        'classroom',
        'tree-planting',
        'health-check',
    ];

    public function run(DemoSeedManifest $manifest): void
    {
        $users = $this->demoUserIds($manifest);
        $mediaIds = $this->demoMediaIds($manifest);
        $complaints = $this->seedComplaints($manifest, $users);

        $this->seedStatusHistories($manifest, $complaints, $users);
        $this->seedNotes($manifest, $complaints, $users);
        $this->seedAttachments($manifest, $complaints, $mediaIds);
        $this->seedReferenceSequence($manifest);
    }

    /**
     * @param  list<int>  $users
     * @return list<int>
     */
    private function seedComplaints(DemoSeedManifest $manifest, array $users): array
    {
        $complaints = [
            [
                'reference' => 'CMP-2026-0001',
                'category' => 'service_delivery',
                'subject' => 'Delayed response to a service request',
                'description' => 'A service request submitted three weeks ago has not received a response. The requester would like an update on the current status and expected timeline.',
                'locale' => 'en',
                'related_project' => 'Community Services Programme',
                'location' => 'Northern districts',
                'status' => 'in_progress',
                'priority' => 'normal',
                'anonymous' => false,
                'name' => 'Hanan Farouk',
                'method' => 'email',
                'email' => 'hanan.farouk@northstar.test',
                'phone' => '+000 30 5550 1421',
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0002',
                'category' => 'staff_conduct',
                'subject' => 'ملاحظة حول تعامل فريق الاستقبال',
                'description' => 'تلاحظ مقدمة البلاغ أن التعامل في مكتب الاستقبال لم يكن واضحًا بخصوص خطوات تقديم الطلب، وتطلب توضيح الإجراء المتبع.',
                'locale' => 'ar',
                'related_project' => 'Public Service Desk',
                'location' => 'Central office',
                'status' => 'waiting_information',
                'priority' => 'high',
                'anonymous' => false,
                'name' => 'سارة العتيبي',
                'method' => 'phone',
                'email' => 'sara.otaibi@greenfield.test',
                'phone' => '+000 20 5550 1466',
                'preferred_locale' => 'ar',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0003',
                'category' => 'assistance',
                'subject' => 'استفسار عن أهلية المساعدة',
                'description' => 'تم تقديم بلاغ مجهول حول عدم وضوح معايير الأهلية في إحدى جولات توزيع المساعدات، مع طلب نشر المعايير بشكل أبسط.',
                'locale' => 'ar',
                'related_project' => 'Assistance Distribution Round',
                'location' => 'Eastern districts',
                'status' => 'resolved',
                'priority' => 'normal',
                'anonymous' => true,
                'name' => null,
                'method' => null,
                'email' => null,
                'phone' => null,
                'preferred_locale' => 'ar',
                'resolved_at' => '2026-08-18 11:30:00',
                'resolution' => 'تم مراجعة معايير الأهلية ونشر ملخص مبسط لها في صفحة البرنامج، مع إحالة الملاحظة إلى فريق التوزيع.',
            ],
            [
                'reference' => 'CMP-2026-0004',
                'category' => 'accessibility',
                'subject' => 'Access to programme documents',
                'description' => 'A community member reported that some programme documents are difficult to download on mobile devices and asked for accessible formats.',
                'locale' => 'en',
                'related_project' => 'Community Services Programme',
                'location' => 'Remote',
                'status' => 'in_progress',
                'priority' => 'low',
                'anonymous' => false,
                'name' => 'Elena Vasquez',
                'method' => 'email',
                'email' => 'elena.vasquez@bayview.test',
                'phone' => '+000 41 5550 1503',
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0005',
                'category' => 'data_privacy',
                'subject' => 'طلب تصحيح بيانات التواصل',
                'description' => 'أبلغ صاحب العلاقة عن وجود رقم هاتف قديم في سجل التواصل، وطلب تحديثه لضمان وصول الإشعارات المتعلقة بالبرنامج.',
                'locale' => 'ar',
                'related_project' => 'Beneficiary Registry',
                'location' => 'Central office',
                'status' => 'resolved',
                'priority' => 'high',
                'anonymous' => false,
                'name' => 'خالد الزهراني',
                'method' => 'email',
                'email' => 'khalid.zahrani@greenfield.test',
                'phone' => '+000 20 5550 1548',
                'preferred_locale' => 'ar',
                'resolved_at' => '2026-07-29 09:45:00',
                'resolution' => 'تم تحديث بيانات التواصل بعد التحقق من الهوية، وتأكيد حذف الرقم القديم من سجل الإشعارات.',
            ],
            [
                'reference' => 'CMP-2026-0006',
                'category' => 'other',
                'subject' => 'General comment on published reports',
                'description' => 'An anonymous comment suggested adding a short glossary to the published reports to make technical terms easier to follow.',
                'locale' => 'en',
                'related_project' => null,
                'location' => null,
                'status' => 'rejected',
                'priority' => 'low',
                'anonymous' => true,
                'name' => null,
                'method' => null,
                'email' => null,
                'phone' => null,
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0007',
                'category' => 'service_delivery',
                'subject' => 'Urgent scheduling issue at a field site',
                'description' => 'The field team was unable to complete a scheduled service visit because the site access arrangements were not confirmed in advance.',
                'locale' => 'en',
                'related_project' => 'Field Response Programme',
                'location' => 'Southern districts',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'anonymous' => false,
                'name' => 'Martin Cole',
                'method' => 'phone',
                'email' => 'martin.cole@riverbend.test',
                'phone' => '+000 44 5550 1582',
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0008',
                'category' => 'assistance',
                'subject' => 'ملاحظة حول جدول التوزيع',
                'description' => 'تعديل موعد التوزيع دون إشعار مسبق تسبب في انتظار طويل. نرجو اعتماد قناة إشعار واضحة عند تغيير المواعيد.',
                'locale' => 'ar',
                'related_project' => 'Assistance Distribution Round',
                'location' => 'Western districts',
                'status' => 'waiting_information',
                'priority' => 'normal',
                'anonymous' => false,
                'name' => 'مريم العبدالله',
                'method' => 'email',
                'email' => 'maryam.abdullah@greenfield.test',
                'phone' => '+000 30 5550 1627',
                'preferred_locale' => 'ar',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0009',
                'category' => 'staff_conduct',
                'subject' => 'طلب مراجعة إجراءات المقابلة',
                'description' => 'بلاغ مجهول يطلب مراجعة طريقة طرح الأسئلة أثناء مقابلة تسجيل المشاركين، مع التأكد من احترام الخصوصية في جميع الخطوات.',
                'locale' => 'ar',
                'related_project' => 'Volunteer Screening',
                'location' => 'Central office',
                'status' => 'resolved',
                'priority' => 'high',
                'anonymous' => true,
                'name' => null,
                'method' => null,
                'email' => null,
                'phone' => null,
                'preferred_locale' => 'ar',
                'resolved_at' => '2026-08-25 14:10:00',
                'resolution' => 'تمت مراجعة نموذج المقابلة مع المشرفين، وتحديث التعليمات الخاصة بخصوصية البيانات.',
            ],
            [
                'reference' => 'CMP-2026-0010',
                'category' => 'accessibility',
                'subject' => 'Venue access for a community meeting',
                'description' => 'A participant requested step-free access and clear signage for an upcoming community meeting at the regional office.',
                'locale' => 'en',
                'related_project' => 'Community Dialogue Series',
                'location' => 'Regional office',
                'status' => 'in_progress',
                'priority' => 'low',
                'anonymous' => false,
                'name' => 'Priya Nair',
                'method' => 'email',
                'email' => 'priya.nair@harborlight.test',
                'phone' => '+000 44 5550 1664',
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
            [
                'reference' => 'CMP-2026-0011',
                'category' => 'service_delivery',
                'subject' => 'إغلاق شكوى مبكرًا',
                'description' => 'اعتراض على إغلاق شكوى سابقة قبل استلام رد مكتوب، مع طلب إعادة فتح الملف ومراجعة المدة الزمنية للرد.',
                'locale' => 'ar',
                'related_project' => 'Public Service Desk',
                'location' => 'Central office',
                'status' => 'resolved',
                'priority' => 'normal',
                'anonymous' => false,
                'name' => 'ليلى منصور',
                'method' => 'email',
                'email' => 'layla.mansour@northstar.test',
                'phone' => '+000 30 5550 1708',
                'preferred_locale' => 'ar',
                'resolved_at' => '2026-08-28 10:00:00',
                'resolution' => 'تمت إعادة مراجعة الملف وإرسال رد مكتوب، وتعديل إجراءات الإغلاق لتشمل توثيق الرد أولًا.',
            ],
            [
                'reference' => 'CMP-2026-0012',
                'category' => 'other',
                'subject' => 'Anonymous suggestion on reporting language',
                'description' => 'An anonymous suggestion asked for shorter summaries at the beginning of each published report.',
                'locale' => 'en',
                'related_project' => null,
                'location' => null,
                'status' => 'in_progress',
                'priority' => 'normal',
                'anonymous' => true,
                'name' => null,
                'method' => null,
                'email' => null,
                'phone' => null,
                'preferred_locale' => 'en',
                'resolved_at' => null,
                'resolution' => null,
            ],
        ];

        $categories = (array) config('complaints.categories', []);
        $statuses = (array) config('complaints.statuses', []);
        $priorities = (array) config('complaints.priorities', []);
        $ids = [];

        foreach ($complaints as $index => $complaint) {
            $category = in_array($complaint['category'], $categories, true) ? $complaint['category'] : 'other';
            $status = in_array($complaint['status'], $statuses, true) ? $complaint['status'] : 'under_review';
            $priority = in_array($complaint['priority'], $priorities, true) ? $complaint['priority'] : 'normal';
            $at = sprintf('2026-08-%02d %02d:00:00', 1 + $index, 8 + ($index % 9));

            $ids[] = (int) $manifest->ensureRow('complaints', 'complaints', 'case:'.$complaint['reference'], [
                'reference' => $complaint['reference'],
                'category' => $category,
                'subject' => $complaint['subject'],
                'description' => $complaint['description'],
                'locale' => $complaint['locale'],
                'related_project' => $complaint['related_project'],
                'location' => $complaint['location'],
                'status' => $status,
                'priority' => $priority,
                'is_anonymous' => $complaint['anonymous'],
                'complainant_name' => $complaint['name'],
                'preferred_contact_method' => $complaint['method'],
                'email' => $complaint['email'],
                'phone' => $complaint['phone'],
                'preferred_locale' => $complaint['preferred_locale'],
                'assigned_to' => $this->assignedUserId($users, $index + 3),
                'resolved_at' => $complaint['resolved_at'],
                'resolution_details' => $complaint['resolution'],
                'ip_hash' => hash('sha256', 'pcdr-demo:complaint:'.$complaint['reference']),
                'user_agent' => 'Mozilla/5.0 (compatible; InstitutionalBrowser/2.0)',
                'deleted_at' => null,
                ...DemoSeedManifest::stamps($at),
            ], ['reference' => $complaint['reference']]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $complaints
     * @param  list<int>  $users
     */
    private function seedStatusHistories(DemoSeedManifest $manifest, array $complaints, array $users): void
    {
        /** @var array<string, list<string>> $transitions */
        $transitions = (array) config('complaints.status_transitions', []);
        $afterReview = $transitions['under_review'] ?? ['in_progress'];

        foreach ($complaints as $index => $complaintId) {
            $finalStatus = $this->finalStatus($index, $afterReview);
            $firstAt = sprintf('2026-08-%02d 09:00:00', 1 + $index);
            $secondAt = sprintf('2026-08-%02d 12:00:00', 1 + $index);

            $manifest->ensureRow('complaint_status_histories', 'complaints', 'status:'.($index + 1).':1', [
                'complaint_id' => $complaintId,
                'from_status' => 'new',
                'to_status' => 'under_review',
                'changed_by' => $this->assignedUserId($users, $index + 3),
                'note' => 'Complaint received and assigned for initial review.',
                ...DemoSeedManifest::stamps($firstAt),
            ], [
                'complaint_id' => $complaintId,
                'from_status' => 'new',
                'to_status' => 'under_review',
            ]);

            $manifest->ensureRow('complaint_status_histories', 'complaints', 'status:'.($index + 1).':2', [
                'complaint_id' => $complaintId,
                'from_status' => 'under_review',
                'to_status' => $finalStatus,
                'changed_by' => $this->assignedUserId($users, $index + 4),
                'note' => $finalStatus === 'resolved'
                    ? 'Review completed and resolution recorded.'
                    : 'Case moved forward after initial assessment.',
                ...DemoSeedManifest::stamps($secondAt),
            ], [
                'complaint_id' => $complaintId,
                'from_status' => 'under_review',
                'to_status' => $finalStatus,
            ]);
        }
    }

    /**
     * @param  list<int>  $complaints
     * @param  list<int>  $users
     */
    private function seedNotes(DemoSeedManifest $manifest, array $complaints, array $users): void
    {
        $notes = [
            ['complaint' => 0, 'body' => 'Confirmed the case details with the service team and requested an updated timeline for the requester.', 'user' => 5, 'at' => '2026-08-02 10:15:00'],
            ['complaint' => 0, 'body' => 'Reminder sent to the responsible unit; response is still within the review window.', 'user' => 5, 'at' => '2026-08-08 09:30:00'],
            ['complaint' => 1, 'body' => 'تم الاتصال بصاحبة البلاغ لطلب توضيح خطوات تقديم الطلب وتاريخ الزيارة.', 'user' => 5, 'at' => '2026-08-03 11:45:00'],
            ['complaint' => 1, 'body' => 'Awaiting the requested details before the case can proceed to the next stage.', 'user' => 6, 'at' => '2026-08-09 14:20:00'],
            ['complaint' => 2, 'body' => 'رُوجعت معايير الأهلية المستخدمة في الجولة الأخيرة وتم توثيق الملاحظة.', 'user' => 5, 'at' => '2026-08-05 08:50:00'],
            ['complaint' => 2, 'body' => 'Published summary reviewed by the programme lead; case ready to close.', 'user' => 6, 'at' => '2026-08-18 11:20:00'],
            ['complaint' => 3, 'body' => 'Accessibility requirement logged with the document team for format review.', 'user' => 6, 'at' => '2026-08-06 13:10:00'],
            ['complaint' => 3, 'body' => 'Requested examples of the documents that are difficult to open on mobile devices.', 'user' => 5, 'at' => '2026-08-12 10:05:00'],
            ['complaint' => 4, 'body' => 'تم التحقق من هوية صاحب العلاقة قبل تحديث بيانات التواصل.', 'user' => 5, 'at' => '2026-08-07 09:40:00'],
            ['complaint' => 4, 'body' => 'Registry update confirmed and old contact number removed.', 'user' => 6, 'at' => '2026-08-29 09:35:00'],
            ['complaint' => 5, 'body' => 'Suggestion reviewed; glossary added to the editorial backlog for future reports.', 'user' => 6, 'at' => '2026-08-10 15:30:00'],
            ['complaint' => 6, 'body' => 'Site access issue escalated to the field coordinator for immediate follow-up.', 'user' => 5, 'at' => '2026-08-11 08:20:00'],
            ['complaint' => 7, 'body' => 'تم مراجعة جدول التوزيع مع المسؤول الميداني وتوثيق سبب التأخير.', 'user' => 5, 'at' => '2026-08-13 12:45:00'],
            ['complaint' => 8, 'body' => 'Interview protocol reviewed with the supervisor; privacy instructions updated.', 'user' => 6, 'at' => '2026-08-15 11:10:00'],
            ['complaint' => 9, 'body' => 'Venue requirements shared with the logistics team; signage is being prepared.', 'user' => 5, 'at' => '2026-08-17 16:00:00'],
            ['complaint' => 10, 'body' => 'تمت إعادة مراجعة ملف الشكوى وإرسال الرد المكتوب لصاحبة العلاقة.', 'user' => 5, 'at' => '2026-08-26 10:40:00'],
            ['complaint' => 11, 'body' => 'Shorter report summaries noted for the next publications review cycle.', 'user' => 6, 'at' => '2026-08-23 13:25:00'],
            ['complaint' => 11, 'body' => 'Case remains open pending the editorial team feedback.', 'user' => 5, 'at' => '2026-08-30 09:55:00'],
        ];

        foreach ($notes as $index => $note) {
            $complaintId = $complaints[$note['complaint']] ?? null;

            if ($complaintId === null) {
                continue;
            }

            $manifest->ensureRow('complaint_notes', 'complaints', 'note:'.$index, [
                'complaint_id' => $complaintId,
                'user_id' => $this->assignedUserId($users, $note['user']),
                'body' => $note['body'],
                'is_internal' => true,
                ...DemoSeedManifest::stamps($note['at']),
            ], [
                'complaint_id' => $complaintId,
                'body' => $note['body'],
            ]);
        }
    }

    /**
     * @param  list<int>  $complaints
     * @param  list<int>  $mediaIds
     */
    private function seedAttachments(DemoSeedManifest $manifest, array $complaints, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        for ($index = 0; $index < 10; $index++) {
            $complaintId = $complaints[$index] ?? null;
            $mediaId = $mediaIds[$index % count($mediaIds)] ?? null;

            if ($complaintId === null || $mediaId === null) {
                continue;
            }

            $manifest->ensurePivot('complaint_attachments', [
                'complaint_id' => $complaintId,
                'media_id' => $mediaId,
            ], [
                'label' => $index % 2 === 0 ? 'Supporting document' : 'مرفق توضيحي',
                ...DemoSeedManifest::stamps(sprintf('2026-08-%02d 10:00:00', 1 + $index)),
            ]);
        }
    }

    private function seedReferenceSequence(DemoSeedManifest $manifest): void
    {
        $manifest->ensureRow('complaint_reference_sequences', 'complaints', 'year:2026', [
            'year' => 2026,
            'next_value' => 13,
            ...DemoSeedManifest::stamps('2026-08-31 09:00:00'),
        ], ['year' => 2026], null, 'year');
    }

    /**
     * @param  list<string>  $afterReview
     */
    private function finalStatus(int $index, array $afterReview): string
    {
        $statuses = ['in_progress', 'waiting_information', 'resolved', 'in_progress', 'resolved', 'rejected', 'in_progress', 'waiting_information', 'resolved', 'in_progress', 'resolved', 'in_progress'];
        $candidate = $statuses[$index] ?? 'in_progress';

        return in_array($candidate, $afterReview, true) ? $candidate : 'in_progress';
    }

    /** @return list<int> */
    private function demoUserIds(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::USER_EMAILS as $email) {
            $value = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.$email);

            if ($value !== null && ctype_digit($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        if ($ids === []) {
            $rows = DB::table('settings')
                ->where('group', DemoSeedManifest::GROUP)
                ->where('key', 'like', 'row:users:%')
                ->orderBy('id')
                ->pluck('value');

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row, true);

                if (is_array($decoded) && isset($decoded['id']) && ctype_digit((string) $decoded['id'])) {
                    $id = (int) $decoded['id'];
                    $ids[$id] = $id;
                }
            }
        }

        return array_values($ids);
    }

    /** @return list<int> */
    private function demoMediaIds(DemoSeedManifest $manifest): array
    {
        $ids = [];

        foreach (self::MEDIA_SLUGS as $slug) {
            $value = $manifest->settingValue(DemoSeedManifest::GROUP, 'key:media:'.$slug);

            if ($value !== null && ctype_digit($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        if ($ids === []) {
            $rows = DB::table('settings')
                ->where('group', DemoSeedManifest::GROUP)
                ->where('key', 'like', 'row:media:%')
                ->orderBy('id')
                ->pluck('value');

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row, true);

                if (is_array($decoded) && isset($decoded['id']) && ctype_digit((string) $decoded['id'])) {
                    $id = (int) $decoded['id'];
                    $ids[$id] = (int) $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>  $users
     */
    private function assignedUserId(array $users, int $index): ?int
    {
        return $users === [] ? null : $users[$index % count($users)];
    }
}
