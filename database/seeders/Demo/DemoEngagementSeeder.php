<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\AuditArchive;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\PartnershipInquiry;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolunteerInquiry;
use App\Notifications\AuditLifecycleNotification;
use App\Notifications\ComplaintStatusChangedNotification;
use App\Notifications\ComplaintSubmittedNotification;
use App\Notifications\InquiryReceivedNotification;
use App\Support\DemoSeedManifest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Internally tracked engagement, delivery, session and audit-lifecycle rows.
 *
 * All public-facing copy is natural institutional language. Ownership lives
 * only in the private demo registry; no visible label is added to content.
 */
final class DemoEngagementSeeder extends Seeder
{
    private const CONTACT_MODEL = ContactMessage::class;

    private const VOLUNTEER_MODEL = VolunteerInquiry::class;

    private const PARTNERSHIP_MODEL = PartnershipInquiry::class;

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

    public function run(DemoSeedManifest $manifest): void
    {
        $users = $this->demoUserIds($manifest);

        $inquiries = [
            'contact' => $this->seedContactMessages($manifest, $users),
            'volunteer' => $this->seedVolunteerInquiries($manifest, $users),
            'partnership' => $this->seedPartnershipInquiries($manifest, $users),
        ];

        $this->seedInquiryNotes($manifest, $inquiries, $users);
        $this->seedInquiryReplies($manifest, $inquiries, $users);
        $this->seedEmailDeliveryLogs($manifest, $inquiries, $users);
        $this->seedNotifications($manifest, $users);
        $this->seedSessions($manifest, $users);
        $this->seedAuditLifecycle($manifest, $users);
    }

    /**
     * @param  list<int>  $users
     * @return list<int>
     */
    private function seedContactMessages(DemoSeedManifest $manifest, array $users): array
    {
        $messages = [
            [
                'seed' => 'contact-1',
                'category' => 'programs',
                'name' => 'ليلى منصور',
                'email' => 'layla.mansour@northstar.test',
                'subject' => 'استفسار عن برنامج التدريب الميداني',
                'message' => 'أود معرفة موعد الدفعة القادمة من التدريب الميداني، وهل التسجيل مفتوح للمشاركين من المناطق الريفية؟',
                'locale' => 'ar',
                'status' => 'in_progress',
                'at' => '2026-08-04 09:20:00',
            ],
            [
                'seed' => 'contact-2',
                'category' => 'general',
                'name' => 'Daniel Reyes',
                'email' => 'daniel.reyes@bayview.test',
                'subject' => 'Request for programme overview',
                'message' => 'Could you share an overview of the current community programmes and the criteria for participation?',
                'locale' => 'en',
                'status' => 'new',
                'at' => '2026-08-06 11:05:00',
            ],
            [
                'seed' => 'contact-3',
                'category' => 'accessibility',
                'name' => 'سامي الخطيب',
                'email' => 'sami.khatib@riverbend.test',
                'subject' => 'ملاحظات حول إمكانية الوصول إلى الموقع',
                'message' => 'بعض صفحات التقارير لا تُقرأ بوضوح باستخدام قارئ الشاشة. أرجو مراجعة ترتيب العناوين والجداول.',
                'locale' => 'ar',
                'status' => 'under_review',
                'at' => '2026-08-09 14:40:00',
            ],
            [
                'seed' => 'contact-4',
                'category' => 'media',
                'name' => 'Amara Okafor',
                'email' => 'amara.okafor@harborlight.test',
                'subject' => 'Media enquiry about the annual report',
                'message' => 'I would like to confirm whether the annual report is available in English and request permission to quote from it.',
                'locale' => 'en',
                'status' => 'resolved',
                'at' => '2026-08-12 08:50:00',
            ],
            [
                'seed' => 'contact-5',
                'category' => 'programs',
                'name' => 'مريم العبدالله',
                'email' => 'maryam.abdullah@greenfield.test',
                'subject' => 'تنسيق زيارة ميدانية',
                'message' => 'نرغب في تنسيق زيارة لفريقنا إلى أحد مواقع العمل، هل يمكن تزويدنا بالجهة المناسبة للتواصل؟',
                'locale' => 'ar',
                'status' => 'waiting_information',
                'at' => '2026-08-17 10:15:00',
            ],
            [
                'seed' => 'contact-6',
                'category' => 'general',
                'name' => 'Thomas Berger',
                'email' => 'thomas.berger@westport.invalid',
                'subject' => 'Clarification on volunteer requirements',
                'message' => 'Please clarify whether previous experience is required for the logistics volunteer team and how training is arranged.',
                'locale' => 'en',
                'status' => 'new',
                'at' => '2026-08-21 16:30:00',
            ],
        ];

        $ids = [];

        foreach ($messages as $index => $message) {
            $reference = DemoSeedManifest::fixedUlid('contact:'.$message['seed']);
            $ids[] = (int) $manifest->ensureRow('contact_messages', 'engagement', 'contact:'.$message['seed'], [
                'reference' => $reference,
                'category' => $message['category'],
                'name' => $message['name'],
                'email' => $message['email'],
                'subject' => $message['subject'],
                'message' => $message['message'],
                'locale' => $message['locale'],
                'consent' => true,
                'ip_hash' => hash('sha256', 'pcdr-demo:contact:'.$message['seed']),
                'user_agent' => 'Mozilla/5.0 (compatible; InstitutionalBrowser/2.0)',
                'status' => $message['status'],
                'assigned_to' => $this->assignedUserId($users, $index + 2),
                ...DemoSeedManifest::stamps($message['at']),
            ], ['reference' => $reference]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $users
     * @return list<int>
     */
    private function seedVolunteerInquiries(DemoSeedManifest $manifest, array $users): array
    {
        $inquiries = [
            [
                'seed' => 'volunteer-1',
                'name' => 'نورة القحطاني',
                'email' => 'noura.qahtani@northstar.test',
                'phone' => '+000 30 5550 1240',
                'area' => 'community_outreach',
                'availability' => 'weekday_mornings',
                'message' => 'أستطيع المساعدة في التواصل مع الأسر وتنظيم الزيارات الميدانية خلال أيام الأسبوع.',
                'locale' => 'ar',
                'status' => 'under_review',
                'at' => '2026-08-05 12:10:00',
            ],
            [
                'seed' => 'volunteer-2',
                'name' => 'Elena Vasquez',
                'email' => 'elena.vasquez@bayview.test',
                'phone' => '+000 41 5550 1281',
                'area' => 'translation',
                'availability' => 'weekends',
                'message' => 'I can help translate Arabic and English materials for community information sessions.',
                'locale' => 'en',
                'status' => 'in_progress',
                'at' => '2026-08-08 09:45:00',
            ],
            [
                'seed' => 'volunteer-3',
                'name' => 'عمر الشمري',
                'email' => 'omar.shamri@riverbend.test',
                'phone' => '+000 20 5550 1317',
                'area' => 'logistics',
                'availability' => 'evenings',
                'message' => 'لدي خبرة في تنظيم المخزون وترتيب عمليات التوزيع، وأود المساعدة في ذلك.',
                'locale' => 'ar',
                'status' => 'new',
                'at' => '2026-08-14 15:25:00',
            ],
            [
                'seed' => 'volunteer-4',
                'name' => 'Priya Nair',
                'email' => 'priya.nair@harborlight.test',
                'phone' => '+000 44 5550 1392',
                'area' => 'field_support',
                'availability' => 'weekends',
                'message' => 'I am available to support field data collection and community needs assessments.',
                'locale' => 'en',
                'status' => 'resolved',
                'at' => '2026-08-19 10:55:00',
            ],
        ];

        $ids = [];

        foreach ($inquiries as $index => $inquiry) {
            $reference = DemoSeedManifest::fixedUlid('volunteer:'.$inquiry['seed']);
            $ids[] = (int) $manifest->ensureRow('volunteer_inquiries', 'engagement', 'volunteer:'.$inquiry['seed'], [
                'reference' => $reference,
                'name' => $inquiry['name'],
                'email' => $inquiry['email'],
                'phone' => $inquiry['phone'],
                'area_of_interest' => $inquiry['area'],
                'availability' => $inquiry['availability'],
                'message' => $inquiry['message'],
                'locale' => $inquiry['locale'],
                'consent' => true,
                'ip_hash' => hash('sha256', 'pcdr-demo:volunteer:'.$inquiry['seed']),
                'status' => $inquiry['status'],
                'assigned_to' => $this->assignedUserId($users, $index + 3),
                ...DemoSeedManifest::stamps($inquiry['at']),
            ], ['reference' => $reference]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $users
     * @return list<int>
     */
    private function seedPartnershipInquiries(DemoSeedManifest $manifest, array $users): array
    {
        $inquiries = [
            [
                'seed' => 'partnership-1',
                'organization' => 'Northstar Community Forum',
                'contact_name' => 'Hanan Farouk',
                'email' => 'hanan.farouk@northstar.test',
                'website' => 'https://www.northstar.test',
                'interest' => 'joint_assessment',
                'message' => 'We would like to explore a joint assessment of community needs in the northern districts.',
                'locale' => 'en',
                'status' => 'in_progress',
                'at' => '2026-08-03 13:30:00',
            ],
            [
                'seed' => 'partnership-2',
                'organization' => 'مجلس التنمية المدني',
                'contact_name' => 'خالد الزهراني',
                'email' => 'khalid.zahrani@greenfield.test',
                'website' => 'https://www.greenfield.test',
                'interest' => 'capacity_building',
                'message' => 'نود بحث فرصة التعاون في تدريب الكوادر المحلية على التخطيط المجتمعي وتوثيق الاحتياجات.',
                'locale' => 'ar',
                'status' => 'under_review',
                'at' => '2026-08-10 08:40:00',
            ],
            [
                'seed' => 'partnership-3',
                'organization' => 'Riverbend Research Group',
                'contact_name' => 'Martin Cole',
                'email' => 'martin.cole@riverbend.test',
                'website' => 'https://research.riverbend.test',
                'interest' => 'research',
                'message' => 'We are preparing a study on recovery programmes and would welcome an institutional contribution.',
                'locale' => 'en',
                'status' => 'new',
                'at' => '2026-08-15 11:50:00',
            ],
            [
                'seed' => 'partnership-4',
                'organization' => 'شبكة ميناء الأمل',
                'contact_name' => 'رنا سليم',
                'email' => 'rana.salem@harborlight.test',
                'website' => 'https://www.harborlight.test',
                'interest' => 'service_delivery',
                'message' => 'نرغب في تنسيق جهود تقديم الخدمات في المناطق الساحلية ضمن خطة مشتركة واضحة الأدوار.',
                'locale' => 'ar',
                'status' => 'resolved',
                'at' => '2026-08-22 14:15:00',
            ],
        ];

        $ids = [];

        foreach ($inquiries as $index => $inquiry) {
            $reference = DemoSeedManifest::fixedUlid('partnership:'.$inquiry['seed']);
            $ids[] = (int) $manifest->ensureRow('partnership_inquiries', 'engagement', 'partnership:'.$inquiry['seed'], [
                'reference' => $reference,
                'organization' => $inquiry['organization'],
                'contact_name' => $inquiry['contact_name'],
                'email' => $inquiry['email'],
                'website' => $inquiry['website'],
                'interest' => $inquiry['interest'],
                'message' => $inquiry['message'],
                'locale' => $inquiry['locale'],
                'consent' => true,
                'ip_hash' => hash('sha256', 'pcdr-demo:partnership:'.$inquiry['seed']),
                'status' => $inquiry['status'],
                'assigned_to' => $this->assignedUserId($users, $index + 4),
                ...DemoSeedManifest::stamps($inquiry['at']),
            ], ['reference' => $reference]);
        }

        return $ids;
    }

    /**
     * @param  array{contact: list<int>, volunteer: list<int>, partnership: list<int>}  $inquiries
     * @param  list<int>  $users
     */
    private function seedInquiryNotes(DemoSeedManifest $manifest, array $inquiries, array $users): void
    {
        $notes = [
            ['kind' => 'contact', 'index' => 0, 'user' => 2, 'at' => '2026-08-04 10:05:00', 'body' => 'Checked the training calendar; the next cohort starts next month. Sending the schedule to the programmes team for confirmation.'],
            ['kind' => 'contact', 'index' => 1, 'user' => 2, 'at' => '2026-08-06 12:20:00', 'body' => 'Requested clarification on which programme areas the sender is most interested in before preparing the overview.'],
            ['kind' => 'contact', 'index' => 2, 'user' => 3, 'at' => '2026-08-09 15:10:00', 'body' => 'تم إحالة الملاحظة إلى فريق المحتوى لمراجعة ترتيب العناوين في صفحات التقارير.'],
            ['kind' => 'contact', 'index' => 3, 'user' => 3, 'at' => '2026-08-12 09:15:00', 'body' => 'Confirmed that the English report is available and approved sharing the published summary. Reply sent.'],
            ['kind' => 'contact', 'index' => 4, 'user' => 2, 'at' => '2026-08-17 11:00:00', 'body' => 'Awaiting the preferred visit date and the name of the coordinating team before arranging the field visit.'],
            ['kind' => 'volunteer', 'index' => 0, 'user' => 4, 'at' => '2026-08-05 13:00:00', 'body' => 'المهارات المذكورة مناسبة لفريق التواصل المجتمعي. سيتم التواصل لتأكيد التدريب الأساسي.'],
            ['kind' => 'volunteer', 'index' => 1, 'user' => 4, 'at' => '2026-08-08 10:30:00', 'body' => 'Translation support confirmed for the information-session materials; availability noted for weekends.'],
            ['kind' => 'volunteer', 'index' => 2, 'user' => 5, 'at' => '2026-08-14 16:05:00', 'body' => 'Logistics experience noted. Inventory and distribution teams have been asked to advise on next steps.'],
            ['kind' => 'partnership', 'index' => 0, 'user' => 5, 'at' => '2026-08-03 14:20:00', 'body' => 'Proposal aligns with the current assessment work. Requested a short concept note before scheduling a meeting.'],
            ['kind' => 'partnership', 'index' => 3, 'user' => 5, 'at' => '2026-08-22 15:00:00', 'body' => 'تم تنسيق الأدوار مع الشريك وإغلاق الطلب بعد الاتفاق على خطة العمل المشتركة.'],
        ];

        foreach ($notes as $index => $note) {
            $inquiryId = $inquiries[$note['kind']][$note['index']] ?? null;

            if ($inquiryId === null) {
                continue;
            }

            $manifest->ensureRow('inquiry_notes', 'engagement', 'note:'.$index, [
                'inquiry_type' => $this->modelFor($note['kind']),
                'inquiry_id' => $inquiryId,
                'user_id' => $this->assignedUserId($users, $note['user']),
                'body' => $note['body'],
                ...DemoSeedManifest::stamps($note['at']),
            ], [
                'inquiry_type' => $this->modelFor($note['kind']),
                'inquiry_id' => $inquiryId,
                'body' => $note['body'],
            ]);
        }
    }

    /**
     * @param  array{contact: list<int>, volunteer: list<int>, partnership: list<int>}  $inquiries
     * @param  list<int>  $users
     */
    private function seedInquiryReplies(DemoSeedManifest $manifest, array $inquiries, array $users): void
    {
        $replies = [
            ['kind' => 'contact', 'index' => 0, 'recipient' => 'layla.mansour@northstar.test', 'locale' => 'ar', 'status' => 'sent', 'at' => '2026-08-04 11:30:00', 'subject' => 'رد على استفسارك حول التدريب الميداني', 'body' => 'مرحبًا ليلى، نود إبلاغك بأن الدفعة القادمة تبدأ في شهر أكتوبر، والتسجيل مفتوح للمشاركين من جميع المناطق. سنرسل تفاصيل التقديم قريبًا.'],
            ['kind' => 'contact', 'index' => 3, 'recipient' => 'amara.okafor@harborlight.test', 'locale' => 'en', 'status' => 'sent', 'at' => '2026-08-12 10:45:00', 'subject' => 'Re: Media enquiry about the annual report', 'body' => 'Dear Amara, the annual report is available in English. You are welcome to quote the published summary with attribution.'],
            ['kind' => 'contact', 'index' => 1, 'recipient' => 'daniel.reyes@bayview.test', 'locale' => 'en', 'status' => 'pending', 'at' => '2026-08-07 09:10:00', 'subject' => 'Additional information about your programme enquiry', 'body' => 'Dear Daniel, could you let us know which programme areas you would like us to cover in the overview?'],
            ['kind' => 'contact', 'index' => 5, 'recipient' => 'thomas.berger@westport.invalid', 'locale' => 'en', 'status' => 'failed', 'at' => '2026-08-21 17:00:00', 'subject' => 'Clarification on volunteer requirements', 'body' => 'Dear Thomas, previous experience is not required; training is arranged before the first field assignment.', 'error' => 'Temporary delivery failure; queued for retry.'],
            ['kind' => 'volunteer', 'index' => 1, 'recipient' => 'elena.vasquez@bayview.test', 'locale' => 'en', 'status' => 'sent', 'at' => '2026-08-08 13:15:00', 'subject' => 'Thank you for your volunteer interest', 'body' => 'Dear Elena, thank you for offering translation support. Our team will contact you before the next information session.'],
            ['kind' => 'volunteer', 'index' => 3, 'recipient' => 'priya.nair@harborlight.test', 'locale' => 'en', 'status' => 'sent', 'at' => '2026-08-19 12:00:00', 'subject' => 'Volunteer placement confirmed', 'body' => 'Dear Priya, your support for field data collection has been noted and the coordination team will follow up with details.'],
            ['kind' => 'partnership', 'index' => 0, 'recipient' => 'hanan.farouk@northstar.test', 'locale' => 'en', 'status' => 'sent', 'at' => '2026-08-04 16:00:00', 'subject' => 'Re: Joint assessment proposal', 'body' => 'Dear Hanan, thank you for the proposal. Please share a short concept note so we can schedule an introductory meeting.'],
            ['kind' => 'partnership', 'index' => 2, 'recipient' => 'martin.cole@riverbend.test', 'locale' => 'en', 'status' => 'queued', 'at' => '2026-08-16 10:20:00', 'subject' => 'Regarding your research enquiry', 'body' => 'Dear Martin, we are reviewing your request and will confirm the available material for your study shortly.'],
        ];

        foreach ($replies as $index => $reply) {
            $inquiryId = $inquiries[$reply['kind']][$reply['index']] ?? null;

            if ($inquiryId === null) {
                continue;
            }

            $sentAt = $reply['status'] === 'sent' ? $reply['at'] : null;
            $manifest->ensureRow('inquiry_replies', 'engagement', 'reply:'.$index, [
                'inquiry_type' => $this->modelFor($reply['kind']),
                'inquiry_id' => $inquiryId,
                'reference' => 'RPLY-'.strtoupper(substr(DemoSeedManifest::fixedUlid('reply:'.$index), 0, 12)),
                'recipient' => $reply['recipient'],
                'sender_user_id' => $this->assignedUserId($users, $index + 2),
                'email_template_id' => null,
                'subject' => $reply['subject'],
                'body' => $reply['body'],
                'locale' => $reply['locale'],
                'status' => $reply['status'],
                'error' => $reply['error'] ?? null,
                'sent_at' => $sentAt,
                ...DemoSeedManifest::stamps($reply['at']),
            ], [
                'inquiry_type' => $this->modelFor($reply['kind']),
                'inquiry_id' => $inquiryId,
                'subject' => $reply['subject'],
            ]);
        }
    }

    /**
     * @param  array{contact: list<int>, volunteer: list<int>, partnership: list<int>}  $inquiries
     * @param  list<int>  $users
     */
    private function seedEmailDeliveryLogs(DemoSeedManifest $manifest, array $inquiries, array $users): void
    {
        /** @var list<string> $templateKeys */
        $templateKeys = array_keys((array) config('email.default_templates', []));

        if ($templateKeys === []) {
            $templateKeys = ['inquiry_received', 'general_response', 'inquiry_reply'];
        }

        $statuses = ['sent', 'queued', 'failed'];
        $locales = ['ar', 'en'];

        for ($index = 0; $index < 20; $index++) {
            $templateKey = $templateKeys[$index % count($templateKeys)];
            $status = $statuses[$index % 3];
            $locale = $locales[$index % 2];
            $recipient = 'recipient'.($index + 1).'@mailbox'.($index % 4 + 1).'.test';
            $idempotencyKey = 'mail-'.DemoSeedManifest::fixedUlid('delivery:'.$index);
            $kind = ['contact', 'volunteer', 'partnership'][$index % 3];
            $inquiryId = $inquiries[$kind][$index % count($inquiries[$kind])] ?? null;
            $at = sprintf('2026-08-%02d %02d:%02d:00', 2 + intdiv($index, 2), 8 + ($index % 8), ($index * 3) % 60);

            $manifest->ensureRow('email_delivery_logs', 'engagement', 'delivery:'.$index, [
                'idempotency_key' => $idempotencyKey,
                'purpose' => substr($templateKey, 0, 48),
                'template_key' => $templateKey,
                'inquiry_type' => $inquiryId === null ? null : $this->modelFor($kind),
                'inquiry_id' => $inquiryId,
                'inquiry_reference' => null,
                'recipient_hash' => hash('sha256', mb_strtolower($recipient)),
                'locale' => $locale,
                'status' => $status,
                'provider' => 'log',
                'provider_message_id' => $status === 'sent' ? 'msg-'.DemoSeedManifest::fixedUlid('provider:'.$index) : null,
                'error_code' => $status === 'failed' ? 'temporary_delivery_failure' : null,
                'attempts' => $status === 'failed' ? 2 : 1,
                'sender_user_id' => $this->assignedUserId($users, $index + 1),
                'queued_at' => $at,
                'sent_at' => $status === 'sent' ? $at : null,
                'failed_at' => $status === 'failed' ? $at : null,
                ...DemoSeedManifest::stamps($at),
            ], ['idempotency_key' => $idempotencyKey]);
        }
    }

    /**
     * @param  list<int>  $users
     */
    private function seedNotifications(DemoSeedManifest $manifest, array $users): void
    {
        $types = [
            InquiryReceivedNotification::class,
            ComplaintSubmittedNotification::class,
            ComplaintStatusChangedNotification::class,
            AuditLifecycleNotification::class,
        ];

        for ($index = 0; $index < 10; $index++) {
            $locale = $index % 2 === 0 ? 'ar' : 'en';
            $data = $locale === 'ar'
                ? [
                    'title' => 'استفسار جديد بانتظار المراجعة',
                    'body' => 'وصل استفسار جديد وتم تسجيله في لوحة إدارة المحتوى.',
                    'reference' => 'REF-'.strtoupper(substr(DemoSeedManifest::fixedUlid('notification:'.$index), 0, 8)),
                ]
                : [
                    'title' => 'New inquiry awaiting review',
                    'body' => 'A new inquiry was recorded and is ready for review in the CMS.',
                    'reference' => 'REF-'.strtoupper(substr(DemoSeedManifest::fixedUlid('notification:'.$index), 0, 8)),
                ];

            $at = sprintf('2026-08-%02d %02d:15:00', 5 + $index, 8 + ($index % 9));
            $manifest->ensureRow('notifications', 'engagement', 'notification:'.$index, [
                'id' => DemoSeedManifest::fixedUlid('notification:'.$index),
                'type' => $types[$index % count($types)],
                'notifiable_type' => User::class,
                'notifiable_id' => $this->assignedUserId($users, $index),
                'data' => $this->encodeJson($data),
                'read_at' => $index % 2 === 0 ? $at : null,
                ...DemoSeedManifest::stamps($at),
            ], ['id' => DemoSeedManifest::fixedUlid('notification:'.$index)], static fn (): ?string => DB::table('notifications')
                ->where('id', DemoSeedManifest::fixedUlid('notification:'.$index))
                ->value('id'), 'id');
        }
    }

    /**
     * @param  list<int>  $users
     */
    private function seedSessions(DemoSeedManifest $manifest, array $users): void
    {
        for ($index = 0; $index < 4; $index++) {
            $id = DemoSeedManifest::fixedUlid('session:'.$index);
            $at = sprintf('2026-08-%02d 08:30:00', 18 + $index);
            $userId = $this->assignedUserId($users, $index + 1);
            $payload = [
                'login_web_'.DemoSeedManifest::fixedUlid('session-login:'.$index) => $userId,
                '_flash' => ['old' => [], 'new' => []],
            ];

            $manifest->ensureRow('sessions', 'engagement', 'session:'.$index, [
                'id' => $id,
                'user_id' => $userId,
                'ip_address' => '192.0.2.'.(10 + $index),
                'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) InstitutionalSession/2.0',
                'payload' => $this->encodeJson($payload),
                'last_activity' => strtotime($at) ?: 0,
                'public_reference' => 'PS-'.substr($id, 0, 16),
                'created_at' => $at,
            ], ['id' => $id], static fn (): ?string => DB::table('sessions')->where('id', $id)->value('id'), 'id');
        }
    }

    /**
     * @param  list<int>  $users
     */
    private function seedAuditLifecycle(DemoSeedManifest $manifest, array $users): void
    {
        $archiveIds = $this->seedAuditArchives($manifest, $users);
        $this->seedAuditCleanupRuns($manifest, $archiveIds, $users);
        $this->seedAuditLogHolds($manifest, $users);
        $this->seedAuditLogs($manifest, $archiveIds, $users);
    }

    /**
     * @param  list<int>  $users
     * @return list<int>
     */
    private function seedAuditArchives(DemoSeedManifest $manifest, array $users): array
    {
        $archives = [
            [
                'period_key' => '2026-06',
                'name' => 'June 2026 audit archive',
                'start' => '2026-06-01 00:00:00',
                'end' => '2026-07-01 00:00:00',
                'cutoff' => '2026-07-01 00:00:00',
                'count' => 12,
                'status' => 'verified',
                'verified_at' => '2026-07-02 09:00:00',
                'error' => null,
            ],
            [
                'period_key' => '2026-07',
                'name' => 'July 2026 audit archive',
                'start' => '2026-07-01 00:00:00',
                'end' => '2026-08-01 00:00:00',
                'cutoff' => '2026-08-01 00:00:00',
                'count' => 12,
                'status' => 'pruned',
                'verified_at' => '2026-08-02 09:00:00',
                'error' => null,
            ],
            [
                'period_key' => '2026-08',
                'name' => 'August 2026 audit archive',
                'start' => '2026-08-01 00:00:00',
                'end' => '2026-09-01 00:00:00',
                'cutoff' => '2026-09-01 00:00:00',
                'count' => 8,
                'status' => 'failed',
                'verified_at' => null,
                'error' => 'Checksum verification did not complete; archive retained for review.',
            ],
        ];

        $ids = [];

        foreach ($archives as $index => $archive) {
            $path = 'internal/audit/archives/'.$archive['period_key'].'/audit-log-manifest.json';
            $ids[] = (int) $manifest->ensureRow('audit_archives', 'audit', 'archive:'.$archive['period_key'], [
                'period_key' => $archive['period_key'],
                'name' => $archive['name'],
                'period_start' => $archive['start'],
                'period_end' => $archive['end'],
                'cutoff_at' => $archive['cutoff'],
                'record_count' => $archive['count'],
                'file_path' => $path,
                'file_size' => 4096 + $index * 512,
                'checksums' => $this->encodeJson(['sha256' => hash('sha256', 'pcdr-demo:archive:'.$archive['period_key'])]),
                'manifest' => $this->encodeJson([
                    'period_key' => $archive['period_key'],
                    'record_count' => $archive['count'],
                    'schema_version' => '1.0',
                ]),
                'status' => $archive['status'],
                'schema_version' => '1.0',
                'error' => $archive['error'],
                'attempts' => $archive['status'] === 'failed' ? 3 : 1,
                'initiated_by' => $this->assignedUserId($users, 4),
                'verified_at' => $archive['verified_at'],
                'pruned_at' => $archive['status'] === 'pruned' ? '2026-08-05 10:00:00' : null,
                'last_cleanup_at' => $archive['status'] === 'verified' ? '2026-07-03 11:00:00' : null,
                ...DemoSeedManifest::stamps('2026-07-01 09:00:00'),
            ], ['period_key' => $archive['period_key']]);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $archiveIds
     * @param  list<int>  $users
     */
    private function seedAuditCleanupRuns(DemoSeedManifest $manifest, array $archiveIds, array $users): void
    {
        $runs = [
            ['archive' => 0, 'seed' => 'cleanup-1', 'status' => 'completed', 'mode' => 'dry_run', 'eligible' => 12, 'deleted' => 0, 'at' => '2026-07-02 10:00:00'],
            ['archive' => 0, 'seed' => 'cleanup-2', 'status' => 'completed', 'mode' => 'execute', 'eligible' => 12, 'deleted' => 12, 'at' => '2026-07-03 11:00:00'],
            ['archive' => 1, 'seed' => 'cleanup-3', 'status' => 'completed', 'mode' => 'execute', 'eligible' => 12, 'deleted' => 12, 'at' => '2026-08-05 10:00:00'],
            ['archive' => 2, 'seed' => 'cleanup-4', 'status' => 'failed', 'mode' => 'execute', 'eligible' => 8, 'deleted' => 0, 'at' => '2026-09-01 10:00:00'],
        ];

        foreach ($runs as $index => $run) {
            $manifest->ensureRow('audit_cleanup_runs', 'audit', 'cleanup:'.$run['seed'], [
                'audit_archive_id' => $archiveIds[$run['archive']] ?? null,
                'status' => $run['status'],
                'mode' => $run['mode'],
                'period_start' => '2026-0'.(6 + $run['archive']).'-01 00:00:00',
                'period_end' => '2026-0'.(7 + $run['archive']).'-01 00:00:00',
                'requested_by' => $this->assignedUserId($users, 4),
                'eligible_count' => $run['eligible'],
                'deleted_count' => $run['deleted'],
                'skipped_hold_count' => $index === 2 ? 1 : 0,
                'skipped_unverified_count' => $run['status'] === 'failed' ? 8 : 0,
                'estimated_bytes' => 4096 + $index * 512,
                'report' => $this->encodeJson([
                    'mode' => $run['mode'],
                    'eligible_count' => $run['eligible'],
                    'deleted_count' => $run['deleted'],
                ]),
                'error' => $run['status'] === 'failed' ? 'Archive verification incomplete; cleanup stopped.' : null,
                'started_at' => $run['at'],
                'finished_at' => $run['at'],
                ...DemoSeedManifest::stamps($run['at']),
            ], [
                'audit_archive_id' => $archiveIds[$run['archive']] ?? null,
                'mode' => $run['mode'],
                'started_at' => $run['at'],
            ]);
        }
    }

    /**
     * @param  list<int>  $users
     */
    private function seedAuditLogHolds(DemoSeedManifest $manifest, array $users): void
    {
        $holds = [
            [
                'seed' => 'hold-1',
                'label' => 'Subject-level retention hold',
                'reason' => 'Retained pending the outcome of a documented review.',
                'scope_type' => 'subject',
                'subject_type' => ContactMessage::class,
                'subject_id' => '1',
                'actor_id' => null,
                'expires_at' => '2026-12-01 00:00:00',
                'released_at' => null,
            ],
            [
                'seed' => 'hold-2',
                'label' => 'Actor-level retention hold',
                'reason' => 'Account activity retained for an internal access review.',
                'scope_type' => 'actor',
                'subject_type' => User::class,
                'subject_id' => '2',
                'actor_id' => $this->assignedUserId($users, 1),
                'expires_at' => '2026-11-15 00:00:00',
                'released_at' => null,
            ],
            [
                'seed' => 'hold-3',
                'label' => 'Period retention hold',
                'reason' => 'Monthly records retained until verification is confirmed.',
                'scope_type' => 'period',
                'subject_type' => AuditArchive::class,
                'subject_id' => '2026-08',
                'actor_id' => null,
                'expires_at' => '2026-10-01 00:00:00',
                'released_at' => '2026-08-20 09:00:00',
            ],
        ];

        foreach ($holds as $index => $hold) {
            $manifest->ensureRow('audit_log_holds', 'audit', 'hold:'.$hold['seed'], [
                'label' => $hold['label'],
                'reason' => $hold['reason'],
                'scope_type' => $hold['scope_type'],
                'subject_type' => $hold['subject_type'],
                'subject_id' => $hold['subject_id'],
                'actor_id' => $hold['actor_id'],
                'period_start' => '2026-08-01 00:00:00',
                'period_end' => '2026-09-01 00:00:00',
                'expires_at' => $hold['expires_at'],
                'released_at' => $hold['released_at'],
                'created_by' => $this->assignedUserId($users, 4),
                'released_by' => $hold['released_at'] === null ? null : $this->assignedUserId($users, 3),
                ...DemoSeedManifest::stamps('2026-08-'.(2 + $index).' 09:30:00'),
            ], ['label' => $hold['label']]);
        }
    }

    /**
     * @param  list<int>  $archiveIds
     * @param  list<int>  $users
     */
    private function seedAuditLogs(DemoSeedManifest $manifest, array $archiveIds, array $users): void
    {
        $subjects = [
            Page::class,
            ContactMessage::class,
            User::class,
            Setting::class,
            AuditArchive::class,
        ];
        $actions = [
            'page.published',
            'contact_message.assigned',
            'user.role_changed',
            'setting.updated',
            'audit_archive.verified',
        ];

        for ($index = 0; $index < 40; $index++) {
            $archiveIndex = $index < 12 ? 0 : ($index < 24 ? 1 : ($index < 32 ? 2 : null));
            $month = $archiveIndex === null ? 9 : 6 + $archiveIndex;
            $at = sprintf('2026-%02d-%02d %02d:%02d:00', $month, 1 + ($index % 27), 8 + ($index % 10), ($index * 7) % 60);
            $subjectType = $subjects[$index % count($subjects)];
            $requestId = 'REQ-'.DemoSeedManifest::fixedUlid('audit-request:'.$index);
            $before = ['status' => 'draft', 'updated_by' => 'editor'];
            $after = ['status' => 'published', 'updated_by' => 'publisher'];

            $manifest->ensureRow('audit_logs', 'audit', 'log:'.$index, [
                'actor_id' => $this->assignedUserId($users, $index),
                'action' => $actions[$index % count($actions)],
                'subject_type' => $subjectType,
                'subject_id' => (string) (1 + $index),
                'before' => $this->encodeJson($before),
                'after' => $this->encodeJson($after),
                'context' => $this->encodeJson([
                    'source' => 'cms',
                    'request_id' => $requestId,
                    'ip_hash' => hash('sha256', 'pcdr-demo:audit:'.$index),
                ]),
                'request_id' => $requestId,
                'archive_id' => $archiveIndex === null ? null : ($archiveIds[$archiveIndex] ?? null),
                'archived_at' => $archiveIndex === null ? null : '2026-'.sprintf('%02d', 7 + $archiveIndex).'-01 09:00:00',
                'created_at' => $at,
            ], ['request_id' => $requestId]);
        }
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

    /**
     * @param  list<int>  $users
     */
    private function assignedUserId(array $users, int $index): ?int
    {
        return $users === [] ? null : $users[$index % count($users)];
    }

    private function modelFor(string $kind): string
    {
        return match ($kind) {
            'contact' => self::CONTACT_MODEL,
            'volunteer' => self::VOLUNTEER_MODEL,
            'partnership' => self::PARTNERSHIP_MODEL,
            default => self::CONTACT_MODEL,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encodeJson(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
