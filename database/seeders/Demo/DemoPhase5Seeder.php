<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Support\DemoSeedManifest;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Internally tracked demo dataset for the Phase-5 operating layer.
 *
 * Every write goes through DB::table(). Append-only tables are inserted with
 * created_at only and are never updated when the demo seeder is rerun.
 */
final class DemoPhase5Seeder extends Seeder
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
        'classroom',
        'community-garden',
        'donation-sorting',
        'food-donations',
        'food-packing',
        'handwashing',
    ];

    private DemoSeedManifest $manifest;

    /** @var list<int|string> */
    private array $userIds = [];

    /** @var list<int|string> */
    private array $mediaIds = [];

    /** @var list<string> */
    private array $notificationIds = [];

    /** @var array<string, list<int|string>> */
    private array $externalIds = [];

    /** @var list<int|string> */
    private array $definitionIds = [];

    /** @var array<string, int|string> */
    private array $versionIds = [];

    /** @var list<int|string> */
    private array $instanceIds = [];

    /** @var list<int|string> */
    private array $taskIds = [];

    /** @var list<int|string|null> */
    private array $taskOwnerIds = [];

    /** @var list<int|string> */
    private array $caseIds = [];

    /** @var list<int|string> */
    private array $integrationIds = [];

    /** @var list<int|string> */
    private array $endpointIds = [];

    /** @var list<int|string> */
    private array $referralIds = [];

    /** @var list<int|string> */
    private array $threadIds = [];

    /** @var list<int|string> */
    private array $commentIds = [];

    /** @var list<int|string|null> */
    private array $commentAuthorIds = [];

    /** @var list<int|string> */
    private array $automationIds = [];

    /** @var list<int|string> */
    private array $reviewIds = [];

    /** @var list<array{type: string, id: int|string}> */
    private array $referralSources = [];

    /** @var list<array{type: string, id: int|string}> */
    private array $referralDestinations = [];

    public function run(DemoSeedManifest $manifest): void
    {
        $this->manifest = $manifest;

        $this->initializeReferences();
        $this->seedWorkflow();
        $this->seedTasks();
        $this->seedAccountability();
        $this->seedIntegrations();
        $this->seedReferrals();
        $this->seedDiscussion();
        $this->seedNotifications();
        $this->seedAutomation();
        $this->seedDocumentReviews();
        $this->seedTranslations();
    }

    private function initializeReferences(): void
    {
        $users = [];

        foreach (self::USER_EMAILS as $email) {
            $tracked = $this->manifest->settingValue(DemoSeedManifest::GROUP, 'key:users:'.$email);

            if ($tracked !== null && $tracked !== '' && ctype_digit($tracked)
                && DB::table('users')->where('id', (int) $tracked)->exists()) {
                $users[] = (int) $tracked;
            }
        }

        $this->userIds = array_values(array_unique($users));

        $media = [];

        foreach (self::MEDIA_SLUGS as $slug) {
            $tracked = $this->manifest->settingValue(DemoSeedManifest::GROUP, 'key:media:'.$slug);

            if ($tracked !== null && $tracked !== '' && ctype_digit($tracked)
                && DB::table('media')->where('id', (int) $tracked)->exists()) {
                $media[] = (int) $tracked;
            }
        }

        $this->mediaIds = array_values(array_unique($media));

        $candidateNotifications = [];

        for ($index = 0; $index < 10; $index++) {
            $candidateNotifications[] = DemoSeedManifest::fixedUlid('notification:'.$index);
        }

        $this->notificationIds = DB::table('notifications')->whereIn('id', $candidateNotifications)
            ->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all();

        $related = [
            'projects' => ['project', 'PRJ-', 4],
            'activities' => ['activities', 'ACT-', 3],
            'partners' => ['partners', 'PART-', 8],
            'interventions' => ['interventions', 'INT-', 6],
        ];

        foreach ($related as $table => [$module, $prefix, $limit]) {
            $ids = [];

            for ($number = 1; $number <= $limit; $number++) {
                $tracked = $this->manifest->settingValue(
                    DemoSeedManifest::GROUP,
                    'key:'.$module.':'.$prefix.$number,
                );

                if ($tracked !== null && $tracked !== '' && ctype_digit($tracked)
                    && DB::table($table)->where('id', (int) $tracked)->exists()) {
                    $ids[] = (int) $tracked;
                }
            }

            $this->externalIds[$table] = $ids;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function at(int $minutes): string
    {
        $shift = ($minutes >= 0 ? '+' : '').$minutes.' minutes';

        return (new DateTimeImmutable(DemoSeedManifest::BASE_TIME))->modify($shift)->format('Y-m-d H:i:s');
    }

    /**
     * @return array{created_at: string, updated_at: string}
     */
    private function stamps(int $minutes = 0): array
    {
        return DemoSeedManifest::stamps($minutes === 0 ? DemoSeedManifest::BASE_TIME : $this->at($minutes));
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function idAt(array $ids, int $index): int|string|null
    {
        if ($ids === []) {
            return null;
        }

        $count = count($ids);

        return $ids[((($index % $count) + $count) % $count)];
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function intAt(array $ids, int $index): ?int
    {
        $id = $this->idAt($ids, $index);

        return $id === null ? null : (int) $id;
    }

    private function userId(int $index): ?int
    {
        return $this->intAt($this->userIds, $index);
    }

    private function mediaId(int $index): ?int
    {
        return $this->intAt($this->mediaIds, $index);
    }

    private function hash(string $seed): string
    {
        return hash('sha256', 'pcdr-workflow:'.$seed);
    }

    private function seedWorkflow(): void
    {
        $definitions = [
            ['key' => 'p5-wf-task-follow-up', 'category' => 'task_follow_up', 'status' => 'active', 'trigger' => 'manual'],
            ['key' => 'p5-wf-document-review', 'category' => 'document_review', 'status' => 'active', 'trigger' => 'document_submitted'],
            ['key' => 'p5-wf-data-quality', 'category' => 'data_quality', 'status' => 'active', 'trigger' => 'record_status_changed'],
            ['key' => 'p5-wf-service-delivery', 'category' => 'service_delivery', 'status' => 'draft', 'trigger' => 'scheduled_review'],
        ];

        foreach ($definitions as $index => $definition) {
            $attributes = [
                'key' => $definition['key'],
                'category' => $definition['category'],
                'status' => $definition['status'],
                'trigger_event' => $definition['trigger'],
                'owner_id' => $this->userId($index),
                'created_by' => $this->userId(1),
            ] + $this->stamps();

            $this->definitionIds[] = $this->manifest->ensureRow(
                'workflow_definitions',
                'phase5',
                'workflow_definition:'.$definition['key'],
                $attributes,
                ['key' => $definition['key']],
            );
        }

        $versionPlan = [
            [0, 1, true],
            [0, 2, false],
            [1, 1, true],
            [1, 2, false],
            [2, 1, true],
            [3, 1, false],
        ];

        foreach ($versionPlan as $index => [$definitionIndex, $version, $published]) {
            $definitionId = $this->definitionIds[$definitionIndex];
            $attributes = [
                'definition_id' => $definitionId,
                'version' => $version,
                'config' => $this->json($this->workflowConfig($definitionIndex, $version)),
                'published_at' => $published ? $this->at(15 + $index) : null,
                'created_by' => $this->userId(1),
            ] + $this->stamps(5 + $index);

            $this->versionIds[$definitionIndex.':'.$version] = $this->manifest->ensureRow(
                'workflow_versions',
                'phase5',
                'workflow_version:'.$definitionIndex.':'.$version,
                $attributes,
                ['definition_id' => $definitionId, 'version' => $version],
            );
        }

        $instancePlan = [
            ['definition' => 0, 'version' => 1, 'status' => 'active', 'order' => 1],
            ['definition' => 0, 'version' => 2, 'status' => 'cancelled', 'order' => 0],
            ['definition' => 1, 'version' => 1, 'status' => 'archived', 'order' => 3],
            ['definition' => 1, 'version' => 2, 'status' => 'in_progress', 'order' => 2],
            ['definition' => 2, 'version' => 1, 'status' => 'waiting', 'order' => 2],
            ['definition' => 2, 'version' => 1, 'status' => 'completed', 'order' => 3],
            ['definition' => 3, 'version' => 1, 'status' => 'suspended', 'order' => 1],
            ['definition' => 3, 'version' => 1, 'status' => 'rejected', 'order' => 2],
        ];

        foreach ($instancePlan as $index => $plan) {
            $reference = sprintf('P5-WF-%04d', $index + 1);
            $attributes = [
                'reference' => $reference,
                'definition_id' => $this->definitionIds[$plan['definition']],
                'version_id' => $this->versionIds[$plan['definition'].':'.$plan['version']],
                'subject_type' => null,
                'subject_id' => null,
                'status' => $plan['status'],
                'current_step_order' => $plan['order'],
                'initiator_id' => $this->userId($index),
                'due_at' => $this->at(2880 + $index * 90),
                'metadata' => $this->json(['source' => 'workflow', 'sequence' => $index + 1]),
            ] + $this->stamps(30 + $index);

            $this->instanceIds[] = $this->manifest->ensureRow(
                'workflow_instances',
                'phase5',
                'workflow_instance:'.$reference,
                $attributes,
                ['reference' => $reference],
            );
        }

        $stepLabels = [
            ['استلام', 'Intake'],
            ['مراجعة', 'Review'],
            ['إغلاق', 'Closure'],
        ];
        $stepKeys = ['intake', 'review', 'close'];
        $statusesByInstance = [
            ['completed', 'in_progress', 'pending'],
            ['cancelled', 'cancelled', 'cancelled'],
            ['completed', 'completed', 'completed'],
            ['completed', 'in_progress', 'pending'],
            ['completed', 'waiting', 'pending'],
            ['completed', 'completed', 'completed'],
            ['completed', 'in_progress', 'pending'],
            ['completed', 'rejected', 'skipped'],
        ];

        foreach ($this->instanceIds as $instanceIndex => $instanceId) {
            foreach ($statusesByInstance[$instanceIndex] as $stepIndex => $status) {
                $stepKey = $stepKeys[$stepIndex];
                $assignmentType = ['user', 'role', 'owner'][$stepIndex];
                $started = in_array($status, ['in_progress', 'waiting', 'completed', 'rejected'], true);
                $finished = in_array($status, ['completed', 'rejected', 'skipped', 'cancelled'], true);
                $attributes = [
                    'instance_id' => $instanceId,
                    'step_key' => $stepKey,
                    'label' => $stepLabels[$stepIndex][0].' / '.$stepLabels[$stepIndex][1],
                    'ordering' => $stepIndex + 1,
                    'assignment_type' => $assignmentType,
                    'assignee_id' => $assignmentType === 'user' ? $this->userId($instanceIndex + $stepIndex) : null,
                    'role_key' => $assignmentType === 'role' ? 'publisher_reviewer' : null,
                    'status' => $status,
                    'due_at' => $this->at(4320 + $instanceIndex * 120 + $stepIndex * 60),
                    'started_at' => $started ? $this->at(90 + $instanceIndex * 30 + $stepIndex * 15) : null,
                    'completed_at' => $finished ? $this->at(150 + $instanceIndex * 30 + $stepIndex * 15) : null,
                    'result' => $status === 'completed'
                        ? 'أُنجزت الخطوة تقنياً / Step completed technically.'
                        : ($status === 'rejected' ? 'أُعيدت للمراجعة / Returned for review.' : null),
                ] + $this->stamps(60 + $instanceIndex * 3 + $stepIndex);

                $this->manifest->ensureRow(
                    'workflow_steps',
                    'phase5',
                    'workflow_step:'.$instanceIndex.':'.$stepKey,
                    $attributes,
                    ['instance_id' => $instanceId, 'step_key' => $stepKey],
                );
            }
        }

        $transitionPlan = [
            [[null, 'draft', 'created'], ['draft', 'active', 'started']],
            [[null, 'draft', 'created'], ['draft', 'cancelled', 'cancelled']],
            [[null, 'draft', 'created'], ['draft', 'archived', 'archived']],
            [[null, 'active', 'initialized'], ['active', 'in_progress', 'started']],
            [[null, 'active', 'initialized'], ['active', 'waiting', 'waiting']],
            [[null, 'in_progress', 'initialized'], ['in_progress', 'completed', 'completed']],
            [[null, 'active', 'initialized'], ['active', 'suspended', 'suspended']],
            [[null, 'in_progress', 'initialized'], ['in_progress', 'rejected', 'rejected']],
        ];

        foreach ($this->instanceIds as $instanceIndex => $instanceId) {
            foreach ($transitionPlan[$instanceIndex] as $sequence => [$fromStatus, $toStatus, $action]) {
                $attributes = [
                    'instance_id' => $instanceId,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'action' => $action,
                    'actor_id' => $this->userId($sequence),
                    'reason' => 'تحديث حالة المسار ضمن خطوات المتابعة المعتادة / Workflow status updated during routine follow-up.',
                    'context' => $this->json(['sequence' => $sequence + 1, 'instance' => $instanceIndex + 1]),
                    'created_at' => $this->at(120 + $instanceIndex * 10 + $sequence * 5),
                ];

                $this->manifest->ensureRow(
                    'workflow_transitions',
                    'phase5',
                    'workflow_transition:'.$instanceIndex.':'.$sequence,
                    $attributes,
                );
            }
        }
    }

    /**
     * @return array{steps: list<array<string, mixed>>, conditions: list<array<string, mixed>>}
     */
    private function workflowConfig(int $definitionIndex, int $version): array
    {
        $labels = [
            ['استلام', 'Intake'],
            ['مراجعة', 'Review'],
            ['إغلاق', 'Closure'],
        ];
        $steps = [];

        foreach ($labels as $index => [$arabic, $english]) {
            $steps[] = [
                'key' => ['intake', 'review', 'close'][$index],
                'label' => $arabic.' / '.$english,
                'label_ar' => $arabic,
                'label_en' => $english,
                'order' => $index + 1,
                'assignment_type' => ['user', 'role', 'owner'][$index],
                'assignee_id' => $index === 0 ? $this->userId(0) : null,
                'role_key' => $index === 1 ? 'publisher_reviewer' : null,
                'due_days' => ($index + 1) * 2,
                'due_at' => null,
                'completion_condition' => null,
                'notification_event' => null,
                'allow_rejection' => $index === 1,
                'allow_return' => false,
                'requires_comment' => $index === 1,
                'metadata' => ['definition' => $definitionIndex, 'version' => $version],
            ];
        }

        return ['steps' => $steps, 'conditions' => []];
    }

    /**
     * @return list<array{arabic: string, english: string}>
     */
    private function taskTitles(): array
    {
        $titles = [
            ['مراجعة خطة التوزيع الميداني', 'Review the field distribution plan'],
            ['تحديث بيانات المخزون', 'Update inventory records'],
            ['متابعة طلبات المتطوعين', 'Follow up volunteer requests'],
            ['مراجعة تقرير الجودة', 'Review the quality report'],
            ['تجهيز مواد التدريب', 'Prepare training materials'],
            ['جدولة زيارة الموقع', 'Schedule the site visit'],
            ['توثيق قرار الفريق', 'Document the team decision'],
            ['مراجعة مؤشرات الأداء', 'Review performance indicators'],
            ['التحقق من سجلات التسليم', 'Verify delivery records'],
            ['تحديث صفحة الشفافية', 'Update the transparency page'],
            ['التنسيق مع الشريك المحلي', 'Coordinate with the local partner'],
            ['مراجعة ملاحظات المجتمع', 'Review community notes'],
            ['إغلاق مهمة المراجعة', 'Close the review item'],
            ['أرشفة المستندات', 'Archive documents'],
            ['متابعة المهلة الزمنية', 'Track the deadline'],
            ['اختبار نموذج الإبلاغ', 'Test the reporting template'],
            ['تجميع ملخص أسبوعي', 'Compile a weekly summary'],
            ['مراجعة سجل الوصول', 'Review access logs'],
            ['تحضير عرض تقديمي', 'Prepare a briefing deck'],
            ['إغلاق الملف الفني', 'Close the technical file'],
        ];

        return array_map(
            static fn (array $title): array => ['arabic' => $title[0], 'english' => $title[1]],
            $titles,
        );
    }

    private function seedTasks(): void
    {
        $statuses = [
            'backlog', 'backlog', 'to_do', 'to_do', 'in_progress', 'in_progress',
            'blocked', 'under_review', 'completed', 'to_do', 'in_progress',
            'under_review', 'completed', 'backlog', 'to_do', 'in_progress',
            'blocked', 'under_review', 'completed', 'cancelled',
        ];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $taskStatuses = [];

        foreach ($this->taskTitles() as $index => $title) {
            $code = sprintf('P5-TASK-%03d', $index + 1);
            $status = $statuses[$index];
            $taskStatuses[] = $status;
            $workflowInstanceId = $index < 8 ? $this->intAt($this->instanceIds, $index) : null;
            $owner = $this->userId($index);
            $progress = match ($status) {
                'completed' => 100,
                'under_review' => 80,
                'in_progress' => 60,
                'blocked' => 35,
                'to_do' => 10,
                default => 0,
            };
            $attributes = [
                'code' => $code,
                'title' => $title['arabic'].' / '.$title['english'],
                'description' => 'متابعة داخلية: '.$title['arabic'].' / Internal follow-up: '.$title['english'].'.',
                'status' => $status,
                'priority' => $priorities[$index % 4],
                'start_at' => $this->at(180 + $index * 15),
                'due_at' => $this->at(5760 + $index * 60),
                'owner_id' => $owner,
                'creator_id' => $this->userId(1),
                'subject_type' => $workflowInstanceId === null ? null : 'workflow_instance',
                'subject_id' => $workflowInstanceId,
                'workflow_instance_id' => $workflowInstanceId,
                'parent_id' => in_array($index, [14, 15, 16], true) ? $this->idAt($this->taskIds, $index - 11) : null,
                'progress_percent' => $progress,
                'completed_at' => $status === 'completed' ? $this->at(300 + $index * 10) : null,
                'archived_at' => $status === 'cancelled' ? $this->at(420 + $index * 10) : null,
            ] + $this->stamps(150 + $index);

            $this->taskIds[] = $this->manifest->ensureRow(
                'tasks',
                'phase5',
                'task:'.$code,
                $attributes,
                ['code' => $code],
            );
            $this->taskOwnerIds[] = $owner;
        }

        foreach ($this->taskIds as $index => $taskId) {
            for ($collaborator = 0; $collaborator < 2; $collaborator++) {
                $collaboratorId = $this->userId($index + $collaborator * 3);

                if ($collaboratorId === null) {
                    continue;
                }

                $this->manifest->ensurePivot('task_collaborators', [
                    'task_id' => $taskId,
                    'user_id' => $collaboratorId,
                ]);
            }
        }

        foreach (array_slice($this->taskIds, 0, 12) as $index => $taskId) {
            $this->manifest->ensureRow(
                'task_assignment_history',
                'phase5',
                'task_assignment:'.($index + 1),
                [
                    'task_id' => $taskId,
                    'from_user_id' => $index === 0 ? null : $this->userId($index - 1),
                    'to_user_id' => $this->userId($index),
                    'actor_id' => $this->userId(1),
                    'created_at' => $this->at(200 + $index * 10),
                ],
            );
        }

        $checklistLabels = [
            ['تأكيد البيانات الأساسية', 'Confirm core data'],
            ['إرفاق الدليل المطلوب', 'Attach required evidence'],
        ];

        foreach ($this->taskIds as $index => $taskId) {
            foreach ($checklistLabels as $position => $label) {
                $done = in_array($taskStatuses[$index], ['completed', 'in_progress', 'under_review'], true)
                    && ($position === 0 || $taskStatuses[$index] === 'completed');
                $attributes = [
                    'task_id' => $taskId,
                    'title' => $label[0].' / '.$label[1],
                    'position' => $position,
                    'is_done' => $done,
                    'completed_by' => $done ? $this->userId($index) : null,
                    'completed_at' => $done ? $this->at(320 + $index * 10 + $position) : null,
                ] + $this->stamps(210 + $index * 2 + $position);

                $this->manifest->ensureRow(
                    'task_checklist_items',
                    'phase5',
                    'task_checklist:'.$index.':'.$position,
                    $attributes,
                );
            }
        }

        foreach (array_slice($this->taskIds, 0, 12) as $index => $taskId) {
            $this->manifest->ensureRow(
                'task_dependencies',
                'phase5',
                'task_dependency:'.$index.':'.($index + 1),
                [
                    'task_id' => $this->taskIds[$index + 1],
                    'depends_on_task_id' => $taskId,
                    'type' => 'finish_to_start',
                    'created_at' => $this->at(240 + $index * 5),
                ],
            );
        }
    }

    private function seedAccountability(): void
    {
        $casePlan = [
            ['category' => 'general_feedback', 'channel' => 'internal', 'status' => 'acknowledged'],
            ['category' => 'service_quality', 'channel' => 'website', 'status' => 'under_triage'],
            ['category' => 'project_feedback', 'channel' => 'internal', 'status' => 'assigned'],
            ['category' => 'accessibility_concern', 'channel' => 'website', 'status' => 'under_review'],
            ['category' => 'general_feedback', 'channel' => 'internal', 'status' => 'waiting_information'],
            ['category' => 'service_quality', 'channel' => 'internal', 'status' => 'resolved'],
        ];
        $summaries = [
            'ملاحظة عامة حول ترتيب جلسات المجتمع / General note about community session scheduling.',
            'ملاحظة حول جودة الخدمة المقدمة في مركز الاستقبال / Service quality note for the reception desk.',
            'ملاحظة مرتبطة بأنشطة المشروع الميداني / Note related to field project activities.',
            'استفسار غير حساس حول إمكانية الوصول للموقع / Non-sensitive accessibility question.',
            'ملاحظة عامة تحتاج إلى تفاصيل إضافية / General note awaiting additional detail.',
            'تمت مراجعة ملاحظة جودة الخدمة تقنياً / Service quality note technically reviewed.',
        ];

        foreach ($casePlan as $index => $plan) {
            $reference = sprintf('P5-ACC-%04d', $index + 1);
            $attributes = [
                'reference' => $reference,
                'category' => $plan['category'],
                'channel' => $plan['channel'],
                'status' => $plan['status'],
                'summary' => $summaries[$index],
                'preferred_contact_method' => null,
                'language_preference' => $index === 2 ? 'en' : 'ar',
                'is_sensitive' => false,
                'assigned_to' => $this->userId($index),
                'due_at' => $this->at(7200 + $index * 120),
                'closed_at' => $plan['status'] === 'resolved' ? $this->at(7600) : null,
            ] + $this->stamps(600 + $index * 20);

            $this->caseIds[] = $this->manifest->ensureRow(
                'accountability_cases',
                'phase5',
                'accountability_case:'.$reference,
                $attributes,
                ['reference' => $reference],
            );
        }

        $eventPlan = [
            [[null, 'received', 'received'], ['received', 'acknowledged', 'acknowledged']],
            [[null, 'acknowledged', 'received'], ['acknowledged', 'under_triage', 'triaged']],
            [[null, 'under_triage', 'received'], ['under_triage', 'assigned', 'assigned']],
            [[null, 'assigned', 'received'], ['assigned', 'under_review', 'review_started']],
            [[null, 'under_review', 'received'], ['under_review', 'waiting_information', 'information_requested']],
            [[null, 'under_review', 'received'], ['under_review', 'resolved', 'resolved']],
        ];

        foreach ($this->caseIds as $index => $caseId) {
            foreach ($eventPlan[$index] as $sequence => [$fromStatus, $toStatus, $action]) {
                $this->manifest->ensureRow(
                    'accountability_events',
                    'phase5',
                    'accountability_event:'.$index.':'.$sequence,
                    [
                        'case_id' => $caseId,
                        'action' => $action,
                        'from_status' => $fromStatus,
                        'to_status' => $toStatus,
                        'actor_id' => $this->userId($index + $sequence),
                        'note' => 'تم تسجيل الحدث في ملف الملاحظة / Event recorded on the feedback case.',
                        'is_sensitive' => false,
                        'created_at' => $this->at(610 + $index * 20 + $sequence * 5),
                    ],
                );
            }
        }
    }

    private function seedIntegrations(): void
    {
        $integrations = [
            ['key' => 'p5-int-task-notifier', 'name' => 'إشعارات المهام الداخلية / Internal task notices', 'provider' => 'internal-mail', 'category' => 'communication'],
            ['key' => 'p5-int-reporting-export', 'name' => 'تصدير التقارير / Reporting export', 'provider' => 'local-reporting', 'category' => 'reporting'],
            ['key' => 'p5-int-partner-sync', 'name' => 'مزامنة الشركاء / Partner sync', 'provider' => 'partner-sandbox', 'category' => 'partner_system'],
            ['key' => 'p5-int-service-clock', 'name' => 'ساعة الخدمة / Service clock', 'provider' => 'utility-clock', 'category' => 'utility'],
        ];

        foreach ($integrations as $index => $integration) {
            $attributes = [
                'key' => $integration['key'],
                'name' => $integration['name'],
                'provider' => $integration['provider'],
                'category' => $integration['category'],
                'metadata' => $this->json(['mode' => 'inert', 'region' => 'local']),
                'enabled' => false,
                'health_status' => 'unknown',
                'last_success_at' => null,
                'last_failure_at' => null,
            ] + $this->stamps(900 + $index);

            $this->integrationIds[] = $this->manifest->ensureRow(
                'integration_registries',
                'phase5',
                'integration:'.$integration['key'],
                $attributes,
                ['key' => $integration['key']],
            );
        }

        $endpointPlan = [
            [
                'name' => 'مستقبل المهام الداخلي / Internal task receiver',
                'url' => 'https://hooks.tasks.pcdr.test/events',
                'events' => ['task.created', 'task.status_changed'],
            ],
            [
                'name' => 'مستقبل التقارير الداخلي / Internal reporting receiver',
                'url' => 'https://reports.ingest.partner.invalid/push',
                'events' => ['workflow.status_changed', 'document.submitted'],
            ],
            [
                'name' => 'مزامنة الشركاء / Partner synchronization',
                'url' => 'https://sync.partners.pcdr.test/callback',
                'events' => ['referral.due_soon', 'accountability.created'],
            ],
            [
                'name' => 'ساعة الخدمة / Service clock',
                'url' => 'https://clock.utility.invalid/notify',
                'events' => ['automation.failed', 'integration.failed'],
            ],
        ];

        foreach ($endpointPlan as $index => $endpoint) {
            $attributes = [
                'integration_id' => $this->integrationIds[$index],
                'name' => $endpoint['name'],
                'url' => $endpoint['url'],
                'events' => $this->json($endpoint['events']),
                'secret_encrypted' => 'inert-placeholder-not-a-real-secret',
                'active' => false,
                'created_by' => $this->userId($index),
                'last_success_at' => null,
                'last_failure_at' => null,
            ] + $this->stamps(910 + $index);

            $this->endpointIds[] = $this->manifest->ensureRow(
                'webhook_endpoints',
                'phase5',
                'webhook_endpoint:'.$index,
                $attributes,
            );
        }

        $deliveryStatuses = ['pending', 'sending', 'delivered', 'failed', 'dead_letter', 'disabled'];
        $endpointEvents = array_column($endpointPlan, 'events');

        foreach ($this->endpointIds as $endpointIndex => $endpointId) {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $sequence = $endpointIndex * 3 + $attempt;
                $status = $deliveryStatuses[$sequence % 6];
                $event = $endpointEvents[$endpointIndex][$attempt % 2];

                $this->manifest->ensureRow(
                    'webhook_deliveries',
                    'phase5',
                    'webhook_delivery:'.$sequence,
                    [
                        'endpoint_id' => $endpointId,
                        'event' => $event,
                        'payload_version' => 1,
                        'payload_hash' => $this->hash('webhook-delivery:'.$sequence),
                        'status' => $status,
                        'attempts' => $status === 'failed' ? 2 : ($status === 'delivered' ? 1 : 0),
                        'response_code' => $status === 'delivered' ? 200 : ($status === 'failed' ? 500 : null),
                        'error_code' => $status === 'failed' ? 'inert_endpoint_error' : null,
                        'next_attempt_at' => in_array($status, ['pending', 'failed'], true) ? $this->at(1000 + $sequence * 10) : null,
                        'delivered_at' => $status === 'delivered' ? $this->at(1001 + $sequence * 10) : null,
                        'created_at' => $this->at(920 + $sequence * 10),
                    ],
                );
            }
        }

        $incomingEvents = [
            'task.created',
            'task.status_changed',
            'workflow.status_changed',
            'referral.due_soon',
            'document.submitted',
            'integration.failed',
        ];

        foreach ($incomingEvents as $index => $event) {
            $this->manifest->ensureRow(
                'incoming_webhook_events',
                'phase5',
                'incoming_webhook_event:'.($index + 1),
                [
                    'integration_id' => $this->integrationIds[$index % count($this->integrationIds)],
                    'event' => $event,
                    'nonce' => sprintf('p5-incoming-nonce-%03d', $index + 1),
                    'payload_hash' => $this->hash('incoming-webhook:'.($index + 1)),
                    'processed' => $index % 2 === 0,
                    'received_at' => $this->at(940 + $index * 15),
                    'created_at' => $this->at(940 + $index * 15),
                ],
            );
        }
    }

    private function seedReferrals(): void
    {
        foreach ($this->externalIds['projects'] as $projectId) {
            $this->referralSources[] = ['type' => 'project', 'id' => $projectId];
        }

        foreach ($this->externalIds['activities'] as $activityId) {
            $this->referralSources[] = ['type' => 'activity', 'id' => $activityId];
        }

        foreach ($this->caseIds as $caseId) {
            $this->referralSources[] = ['type' => 'accountability_case', 'id' => $caseId];
        }

        $activePartners = DB::table('partners')
            ->whereIn('id', $this->externalIds['partners'] ?? [])
            ->whereNull('deleted_at')
            ->where('relationship_status', 'active')
            ->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($activePartners as $partnerId) {
            $this->referralDestinations[] = ['type' => 'partner', 'id' => $partnerId];
        }

        $activeUsers = DB::table('users')
            ->whereIn('id', $this->userIds)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($activeUsers as $userId) {
            $this->referralDestinations[] = ['type' => 'internal_team', 'id' => $userId];
        }

        $eligibleServices = DB::table('interventions')
            ->whereIn('id', $this->externalIds['interventions'] ?? [])
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('id')->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($eligibleServices as $serviceId) {
            $this->referralDestinations[] = ['type' => 'service', 'id' => $serviceId];
        }

        if ($this->referralSources === [] || $this->referralDestinations === []) {
            return;
        }

        $statuses = ['submitted', 'accepted', 'in_progress', 'completed', 'closed', 'rejected'];
        $reasons = [
            'إحالة تقنية لمراجعة خطة ميدانية / Technical referral for a field plan review.',
            'إحالة تقنية لتنسيق جودة الخدمة / Technical referral for service quality coordination.',
            'إحالة تقنية لمراجعة مؤشرات المشروع / Technical referral for project indicator review.',
            'إحالة تقنية لاستكمال ملف النشاط / Technical referral to complete an activity file.',
            'إحالة تقنية مكتملة للسجل الداخلي / Completed technical referral for the internal record.',
            'إحالة تقنية خارج النطاق / Technical referral outside the current scope.',
        ];

        foreach ($statuses as $index => $status) {
            $reference = sprintf('P5-REF-%04d', $index + 1);
            $source = $this->referralSources[$index % count($this->referralSources)];
            $destination = $this->referralDestinations[$index % count($this->referralDestinations)];
            $attributes = [
                'reference' => $reference,
                'source_type' => $source['type'],
                'source_id' => $source['id'],
                'destination_type' => $destination['type'],
                'destination_id' => $destination['id'],
                'reason' => $reasons[$index],
                'status' => $status,
                'assigned_to' => $this->userId($index),
                'created_by' => $this->userId(1),
                'due_at' => $this->at(10080 + $index * 120),
                'response_at' => in_array($status, ['accepted', 'in_progress', 'completed', 'closed'], true)
                    ? $this->at(10200 + $index * 120)
                    : null,
                'outcome' => $status === 'completed'
                    ? 'أُنجزت الإحالة تقنياً / Referral completed technically.'
                    : null,
                'closure_reason' => in_array($status, ['closed', 'rejected'], true)
                    ? 'أُغلق السجل التقني / Technical record closed.'
                    : null,
            ] + $this->stamps(1200 + $index * 20);

            $this->referralIds[] = $this->manifest->ensureRow(
                'referrals',
                'phase5',
                'referral:'.$reference,
                $attributes,
                ['reference' => $reference],
            );
        }

        $eventPlan = [
            [[null, 'draft', 'created'], ['draft', 'submitted', 'submitted']],
            [[null, 'submitted', 'created'], ['submitted', 'accepted', 'accepted']],
            [[null, 'accepted', 'created'], ['accepted', 'in_progress', 'started']],
            [[null, 'in_progress', 'created'], ['in_progress', 'completed', 'completed']],
            [[null, 'completed', 'created'], ['completed', 'closed', 'closed']],
            [[null, 'submitted', 'created'], ['submitted', 'rejected', 'rejected']],
        ];

        foreach ($this->referralIds as $index => $referralId) {
            foreach ($eventPlan[$index] as $sequence => [$fromStatus, $toStatus, $action]) {
                $this->manifest->ensureRow(
                    'referral_events',
                    'phase5',
                    'referral_event:'.$index.':'.$sequence,
                    [
                        'referral_id' => $referralId,
                        'action' => $action,
                        'from_status' => $fromStatus,
                        'to_status' => $toStatus,
                        'actor_id' => $this->userId($index + $sequence),
                        'note' => 'سجل إحالة تقني / Technical referral log entry.',
                        'created_at' => $this->at(1210 + $index * 20 + $sequence * 5),
                    ],
                );
            }
        }
    }

    private function seedDiscussion(): void
    {
        $resources = [];

        foreach (array_slice($this->taskIds, 0, 4) as $taskId) {
            $resources[] = ['type' => 'task', 'id' => $taskId];
        }

        foreach (array_slice($this->instanceIds, 0, 2) as $instanceId) {
            $resources[] = ['type' => 'workflow_instance', 'id' => $instanceId];
        }

        if (isset($this->caseIds[0])) {
            $resources[] = ['type' => 'accountability_case', 'id' => $this->caseIds[0]];
        }

        if (isset($this->referralIds[0])) {
            $resources[] = ['type' => 'referral', 'id' => $this->referralIds[0]];
        }

        foreach ($resources as $index => $resource) {
            $attributes = [
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'created_by' => $this->userId($index),
            ] + $this->stamps(1500 + $index);

            $this->threadIds[] = $this->manifest->ensureRow(
                'discussion_threads',
                'phase5',
                'discussion_thread:'.$resource['type'].':'.$resource['id'],
                $attributes,
                ['resource_type' => $resource['type'], 'resource_id' => $resource['id']],
            );
        }

        $bodies = [
            'ملاحظة تمهيدية حول هذا السجل / Opening note on this record.',
            'دراسة داخلية للحالة / Internal review of the item.',
            'تم تحديث السجل تقنياً / The record was updated technically.',
        ];

        foreach ($this->threadIds as $threadIndex => $threadId) {
            $parentId = null;

            foreach ($bodies as $sequence => $body) {
                $authorId = $this->userId($threadIndex + $sequence);
                $attributes = [
                    'thread_id' => $threadId,
                    'parent_id' => $parentId,
                    'author_id' => $authorId,
                    'body' => $body,
                    'edited_at' => $sequence === 1 ? $this->at(1540 + $threadIndex * 10) : null,
                    'deleted_at' => null,
                ] + $this->stamps(1520 + $threadIndex * 10 + $sequence);

                $commentId = $this->manifest->ensureRow(
                    'discussion_comments',
                    'phase5',
                    'discussion_comment:'.$threadIndex.':'.$sequence,
                    $attributes,
                );

                $this->commentIds[] = $commentId;
                $this->commentAuthorIds[] = $authorId;

                if ($sequence === 0) {
                    $parentId = $commentId;
                }
            }
        }

        foreach (array_slice($this->commentIds, 0, 10) as $index => $commentId) {
            $mentioned = $this->userId($index + 2);

            if ($mentioned === $this->commentAuthorIds[$index]) {
                $mentioned = $this->userId($index + 3);
            }

            if ($mentioned === null || $mentioned === $this->commentAuthorIds[$index]) {
                continue;
            }

            $this->manifest->ensurePivot(
                'comment_mentions',
                ['comment_id' => $commentId, 'mentioned_user_id' => $mentioned],
                ['notified_at' => $this->at(1600 + $index)],
            );
        }

        foreach ($this->threadIds as $index => $threadId) {
            for ($follower = 0; $follower < 2; $follower++) {
                $userId = $this->userId($index + $follower + 1);

                if ($userId === null) {
                    continue;
                }

                $this->manifest->ensurePivot('resource_followers', [
                    'resource_type' => $resources[$index]['type'],
                    'resource_id' => $resources[$index]['id'],
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function seedNotifications(): void
    {
        $categories = ['task', 'workflow', 'document', 'system'];
        $digests = ['instant', 'daily', 'weekly'];

        for ($userIndex = 0; $userIndex < 5; $userIndex++) {
            $userId = $this->userId($userIndex);

            if ($userId === null) {
                continue;
            }

            foreach ($categories as $categoryIndex => $category) {
                $channel = $category === 'system' || $categoryIndex % 2 === 0 ? 'database' : 'mail';

                $this->manifest->ensurePivot(
                    'notification_preferences',
                    ['user_id' => $userId, 'category' => $category, 'channel' => $channel],
                    [
                        'enabled' => ! ($userIndex === 4 && $categoryIndex === 1),
                        'digest' => $digests[$categoryIndex % 3],
                        'quiet_start' => $categoryIndex === 0 ? '20:00' : null,
                        'quiet_end' => $categoryIndex === 0 ? '07:00' : null,
                    ],
                );
            }
        }

        $channels = ['database', 'mail', 'sms', 'whatsapp'];
        $statuses = ['pending', 'queued', 'sending', 'sent', 'delivered', 'failed', 'unknown', 'disabled'];
        $notificationCount = count($this->notificationIds);

        for ($index = 0; $index < 30; $index++) {
            $status = $statuses[$index % 8];
            $channel = $channels[$index % 4];
            $sent = in_array($status, ['sent', 'delivered'], true);
            $attributes = [
                'notification_id' => $notificationCount === 0 ? null : $this->notificationIds[$index % $notificationCount],
                'user_id' => $this->userId($index),
                'channel' => $channel,
                'provider' => $channel === 'mail' ? 'mail' : ($channel === 'database' ? 'database' : 'none'),
                'status' => $status,
                'provider_reference' => $sent ? sprintf('delivery-ref-%03d', $index + 1) : null,
                'attempts' => $status === 'failed' ? 2 : ($sent ? 1 : 0),
                'error_code' => $status === 'failed' ? 'inert_delivery_error' : null,
                'queued_at' => in_array($status, ['queued', 'sending', 'sent', 'delivered', 'failed'], true)
                    ? $this->at(1800 + $index)
                    : null,
                'sent_at' => $sent ? $this->at(1801 + $index) : null,
                'delivered_at' => $status === 'delivered' ? $this->at(1802 + $index) : null,
            ] + $this->stamps(1800 + $index);

            $this->manifest->ensureRow(
                'notification_deliveries',
                'phase5',
                'notification_delivery:'.($index + 1),
                $attributes,
            );
        }
    }

    /**
     * @return list<array{key: string, trigger: string, conditions: list<array<string, mixed>>, actions: list<array<string, mixed>>}>
     */
    private function automationDefinitions(): array
    {
        return [
            [
                'key' => 'p5-auto-task-overdue',
                'trigger' => 'task.overdue',
                'conditions' => [['field' => 'task.status', 'operator' => 'not_equals', 'value' => 'completed']],
                'actions' => [[
                    'type' => 'create_notification',
                    'category' => 'task',
                    'priority' => 'high',
                    'title_key' => 'task.overdue.title',
                    'preview_key' => 'task.overdue.preview',
                ]],
            ],
            [
                'key' => 'p5-auto-step-due',
                'trigger' => 'workflow.step_due_soon',
                'conditions' => [['field' => 'workflow.status', 'operator' => 'equals', 'value' => 'in_progress']],
                'actions' => [[
                    'type' => 'add_internal_comment',
                    'body' => 'تذكير تقني بموعد الخطوة / Technical step due reminder.',
                ]],
            ],
            [
                'key' => 'p5-auto-document-submitted',
                'trigger' => 'document.submitted',
                'conditions' => [['field' => 'document_review.status', 'operator' => 'equals', 'value' => 'submitted']],
                'actions' => [[
                    'type' => 'create_task',
                    'title' => 'مراجعة المستند المقدم / Review the submitted document',
                    'priority' => 'normal',
                    'due_days' => 3,
                ]],
            ],
            [
                'key' => 'p5-auto-integration-failed',
                'trigger' => 'integration.failed',
                'conditions' => [],
                'actions' => [[
                    'type' => 'log_audit',
                    'action' => 'integration.failure.logged',
                ]],
            ],
        ];
    }

    private function seedAutomation(): void
    {
        $definitions = $this->automationDefinitions();

        foreach ($definitions as $index => $definition) {
            $attributes = [
                'key' => $definition['key'],
                'category' => 'generic',
                'trigger_event' => $definition['trigger'],
                'enabled' => false,
                'conditions' => $this->json($definition['conditions']),
                'actions' => $this->json($definition['actions']),
                'owner_id' => $this->userId($index),
                'version' => 1,
                'retry_limit' => 3,
                'timeout_seconds' => 20,
                'last_executed_at' => null,
            ] + $this->stamps(2000 + $index);

            $this->automationIds[] = $this->manifest->ensureRow(
                'automation_definitions',
                'phase5',
                'automation_definition:'.$definition['key'],
                $attributes,
                ['key' => $definition['key']],
            );
        }

        $statuses = ['pending', 'queued', 'running', 'succeeded', 'failed', 'dead_letter'];

        foreach ($definitions as $index => $definition) {
            $definitionId = $this->automationIds[$index];

            for ($run = 0; $run < 3; $run++) {
                $sequence = $index * 3 + $run;
                $status = $statuses[$sequence % 6];
                $started = in_array($status, ['running', 'succeeded', 'failed', 'dead_letter'], true);
                $finished = in_array($status, ['succeeded', 'failed', 'dead_letter'], true);
                $idempotencyKey = sprintf('P5-AUTO-EXEC-%03d', $sequence + 1);
                $attributes = [
                    'definition_id' => $definitionId,
                    'definition_version' => 1,
                    'trigger_event' => $definition['trigger'],
                    'subject_type' => $sequence % 2 === 0 ? 'task' : 'workflow_instance',
                    'subject_id' => $this->idAt(
                        $sequence % 2 === 0 ? $this->taskIds : $this->instanceIds,
                        $sequence,
                    ),
                    'idempotency_key' => $idempotencyKey,
                    'status' => $status,
                    'attempts' => in_array($status, ['failed', 'dead_letter'], true) ? 3 : 1,
                    'last_error' => in_array($status, ['failed', 'dead_letter'], true)
                        ? 'inert automation error'
                        : null,
                    'context' => $this->json(['sequence' => $sequence + 1, 'mode' => 'scheduled']),
                    'started_at' => $started ? $this->at(2020 + $sequence * 10) : null,
                    'finished_at' => $finished ? $this->at(2021 + $sequence * 10) : null,
                ] + $this->stamps(2010 + $sequence * 10);

                $this->manifest->ensureRow(
                    'automation_executions',
                    'phase5',
                    'automation_execution:'.$idempotencyKey,
                    $attributes,
                    ['idempotency_key' => $idempotencyKey],
                );
            }
        }
    }

    private function seedDocumentReviews(): void
    {
        $reviews = [
            ['status' => 'submitted', 'version' => 1, 'subject' => 'task'],
            ['status' => 'under_review', 'version' => 1, 'subject' => 'task'],
            ['status' => 'changes_requested', 'version' => 1, 'subject' => 'task'],
            ['status' => 'resubmitted', 'version' => 2, 'subject' => 'project'],
            ['status' => 'reviewed', 'version' => 2, 'subject' => 'project'],
            ['status' => 'archived', 'version' => 2, 'subject' => 'project'],
        ];
        $titles = [
            ['خطة التوزيع الميداني', 'Field distribution plan'],
            ['تقرير الجودة الداخلي', 'Internal quality report'],
            ['نموذج تقييم النشاط', 'Activity assessment form'],
            ['ملخص مؤشرات المشروع', 'Project indicator summary'],
            ['وثيقة المراجعة النهائية', 'Final review document'],
            ['سجل المراجعة المؤرشف', 'Archived review record'],
        ];

        foreach ($reviews as $index => $review) {
            if ($this->mediaId($index) === null) {
                continue;
            }

            $reference = sprintf('P5-DOCREV-%04d', $index + 1);
            $subjectId = $review['subject'] === 'task'
                ? $this->idAt($this->taskIds, $index)
                : $this->idAt($this->externalIds['projects'], $index);
            $attributes = [
                'reference' => $reference,
                'media_id' => $this->mediaId($index),
                'document_link_id' => null,
                'subject_type' => $subjectId === null ? null : $review['subject'],
                'subject_id' => $subjectId,
                'title' => $titles[$index][0].' / '.$titles[$index][1],
                'description' => 'مراجعة وثيقة العمل المقدمة ضمن المسار المعتاد / Review of the submitted working document.',
                'status' => $review['status'],
                'version' => $review['version'],
                'reviewer_id' => $this->userId($index + 1),
                'submitter_id' => $this->userId($index),
                'due_at' => $this->at(14400 + $index * 120),
                'reviewed_at' => in_array($review['status'], ['reviewed', 'archived'], true)
                    ? $this->at(14600 + $index * 120)
                    : null,
                'outcome_note' => $review['status'] === 'reviewed'
                    ? 'تمت المراجعة تقنياً / Reviewed technically.'
                    : null,
            ] + $this->stamps(2400 + $index * 20);

            $this->reviewIds[] = $this->manifest->ensureRow(
                'document_reviews',
                'phase5',
                'document_review:'.$reference,
                $attributes,
                ['reference' => $reference],
            );
        }

        $eventPlan = [
            [[null, 'draft', 'created'], ['draft', 'submitted', 'submitted']],
            [[null, 'submitted', 'created'], ['submitted', 'under_review', 'review_started']],
            [[null, 'under_review', 'created'], ['under_review', 'changes_requested', 'changes_requested']],
            [[null, 'changes_requested', 'created'], ['changes_requested', 'resubmitted', 'resubmitted']],
            [[null, 'resubmitted', 'created'], ['resubmitted', 'reviewed', 'reviewed']],
            [[null, 'reviewed', 'created'], ['reviewed', 'archived', 'archived']],
        ];

        foreach ($this->reviewIds as $index => $reviewId) {
            foreach ($eventPlan[$index] as $sequence => [$fromStatus, $toStatus, $action]) {
                $this->manifest->ensureRow(
                    'document_review_events',
                    'phase5',
                    'document_review_event:'.$index.':'.$sequence,
                    [
                        'review_id' => $reviewId,
                        'action' => $action,
                        'from_status' => $fromStatus,
                        'to_status' => $toStatus,
                        'actor_id' => $this->userId($index + $sequence),
                        'note' => 'سجل مراجعة تقني / Technical review log entry.',
                        'revision' => $sequence + 1,
                        'created_at' => $this->at(2410 + $index * 20 + $sequence * 5),
                    ],
                );
            }
        }
    }

    private function seedTranslations(): void
    {
        $definitionNames = [
            ['مسار متابعة المهام', 'Task follow-up workflow'],
            ['مسار مراجعة المستندات', 'Document review workflow'],
            ['مسار جودة البيانات', 'Data quality workflow'],
            ['مسار تقديم الخدمة', 'Service delivery workflow'],
        ];
        $definitionDescriptions = [
            'متابعة المهام وتنسيق الخطوات تقنياً / Technically coordinate task follow-up steps.',
            'مراجعة المستندات داخل المسار التقني / Review documents within the technical workflow.',
            'التحقق من جودة البيانات المسجلة / Check the quality of recorded data.',
            'تنظيم خطوات تقديم الخدمة تقنياً / Organize technical service delivery steps.',
        ];

        foreach ($definitionNames as $index => $names) {
            foreach (['ar' => 0, 'en' => 1] as $locale => $variant) {
                $ownerId = $this->definitionIds[$index];
                $attributes = [
                    'resource' => 'workflow_definition',
                    'owner_id' => $ownerId,
                    'locale' => $locale,
                    'name' => $names[$variant],
                    'description' => $definitionDescriptions[$index],
                ] + $this->stamps(3000 + $index);

                $this->manifest->ensureRow(
                    'phase5_translations',
                    'phase5',
                    'translation:workflow_definition:'.$ownerId.':'.$locale,
                    $attributes,
                    ['resource' => 'workflow_definition', 'owner_id' => $ownerId, 'locale' => $locale],
                );
            }
        }

        $taskNames = [
            ['مراجعة خطة التوزيع', 'Distribution plan review'],
            ['تحديث بيانات المخزون', 'Inventory records update'],
        ];

        foreach ($taskNames as $index => $names) {
            $locale = $index === 0 ? 'ar' : 'en';
            $ownerId = $this->taskIds[$index];
            $attributes = [
                'resource' => 'task',
                'owner_id' => $ownerId,
                'locale' => $locale,
                'name' => $names[$index === 0 ? 0 : 1],
                'description' => 'ترجمة مهمة العمل الداخلية / Translation of the internal work item.',
            ] + $this->stamps(3020 + $index);

            $this->manifest->ensureRow(
                'phase5_translations',
                'phase5',
                'translation:task:'.$ownerId.':'.$locale,
                $attributes,
                ['resource' => 'task', 'owner_id' => $ownerId, 'locale' => $locale],
            );
        }
    }
}
