<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Permission catalog
|--------------------------------------------------------------------------
|
| This catalog is the code-owned source of truth for known permissions. The
| `pcdr:permissions:sync` command creates or refreshes only the permissions
| marked `dashboard_supported`. Unsupported, reserved, hidden, and historical
| rows are deliberately left untouched so existing assignments are preserved.
|
| The dashboard permission list is an explicit allowlist. A capability is not
| assignable merely because it appears somewhere in the codebase. Permission
| definitions are never created or modified from the dashboard.
|
*/

$supported = [
    'users.view', 'users.create', 'users.update', 'users.delete',
    'users.assign_roles', 'users.reset_password',
    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
    'permissions.view',
    'settings.view', 'settings.update', 'audit_logs.view', 'audit_logs.export',
    'news.view', 'news.create', 'news.update', 'news.delete', 'news.publish', 'news.unpublish',
    'programs.view', 'programs.create', 'programs.update', 'programs.delete', 'programs.publish', 'programs.unpublish',
    'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.publish', 'projects.unpublish',
    'publications.view', 'publications.create', 'publications.update', 'publications.delete',
    'publications.publish', 'publications.unpublish',
    'pillars.view', 'pillars.create', 'pillars.update', 'pillars.delete', 'pillars.publish',
    'media.view', 'media.upload', 'media.update', 'media.delete',
    'directory_partners.view', 'directory_partners.create', 'directory_partners.update',
    'directory_partners.delete', 'directory_partners.publish', 'directory_partners.unpublish',
    'inquiries.view', 'inquiries.view_all', 'inquiries.view_assigned',
    'inquiries.assign', 'inquiries.add_internal_note', 'inquiries.reply',
    'email_templates.view', 'email_templates.create', 'email_templates.update', 'email_templates.delete',
    'inquiries.update',
];

$moduleFor = static function (string $key): string {
    if (str_starts_with($key, 'documents.review') || str_starts_with($key, 'documents.submit_review')) {
        return 'phase5';
    }

    // Preserve the original workflow-reserved module for this historical key.
    if ($key === 'inquiries.update') {
        return 'content';
    }

    $prefix = strstr($key, '.', true) ?: $key;

    return match ($prefix) {
        'users', 'roles', 'permissions' => 'iam',
        'settings', 'audit_logs', 'audit_archives' => 'platform',
        'news', 'programs', 'projects', 'publications', 'pillars', 'media',
        'directory_partners', 'inquiries', 'email_templates' => 'website',
        'content' => 'content',
        'locations', 'interventions', 'activities', 'distributions', 'inventory',
        'volunteers', 'committees', 'partners', 'partner_contacts', 'grants',
        'meal', 'assessments', 'reports', 'documents' => 'operations',
        'workflows', 'tasks', 'collaboration', 'accountability', 'referrals',
        'notifications', 'automations', 'calendar', 'integrations', 'dashboards' => 'phase5',
        default => 'general',
    };
};

$categoryFor = static function (string $key, string $module): string {
    if ($key === 'inquiries.update') {
        return 'website';
    }

    if (in_array($key, [
        'roles.grant_protected',
        'permissions.create',
        'permissions.update',
        'permissions.delete',
        'audit_archives.manage',
        'audit_archives.cleanup',
    ], true)) {
        return 'internal';
    }

    return match ($module) {
        'iam' => 'identity_access',
        'platform' => 'platform_administration',
        'website' => 'website',
        'content' => 'workflow_reserved',
        'operations' => 'operations',
        'phase5' => 'phase5',
        default => 'general',
    };
};

$arabicActions = [
    'view' => 'عرض',
    'view_all' => 'عرض الكل',
    'view_assigned' => 'عرض المُخصص',
    'view_sensitive' => 'عرض الملاحظات الحساسة',
    'create' => 'إنشاء',
    'update' => 'تعديل',
    'delete' => 'حذف',
    'publish' => 'نشر',
    'unpublish' => 'إلغاء نشر',
    'archive' => 'أرشفة',
    'upload' => 'رفع',
    'download' => 'تنزيل',
    'assign' => 'تعيين',
    'assign_roles' => 'تعيين أدوار',
    'reset_password' => 'إعادة تعيين كلمة مرور',
    'transition' => 'تغيير حالة',
    'reconcile' => 'تسوية',
    'receive' => 'استلام',
    'issue' => 'صرف',
    'transfer' => 'نقل',
    'adjust' => 'تعديل',
    'reverse' => 'عكس',
    'verify' => 'تحقق',
    'review' => 'مراجعة',
    'submit_review' => 'إرسال للمراجعة',
    'submit' => 'تسجيل',
    'moderate' => 'إشراف',
    'comment' => 'إضافة تعليق',
    'mention' => 'إشارة إلى',
    'reply' => 'رد على',
    'add_internal_note' => 'إضافة ملاحظة داخلية',
    'export' => 'تصدير',
    'manage' => 'إدارة',
    'cleanup' => 'تنظيف',
    'grant_protected' => 'منح صلاحيات محمية',
    'preferences' => 'إعدادات',
    'execute' => 'تشغيل',
    'retry' => 'إعادة محاولة',
];

$arabicResources = [
    'users' => 'المستخدمين',
    'roles' => 'الأدوار',
    'permissions' => 'الصلاحيات',
    'settings' => 'الإعدادات',
    'audit_logs' => 'سجلات التدقيق',
    'audit_archives' => 'أرشيف سجلات التدقيق',
    'content' => 'المحتوى',
    'news' => 'الأخبار',
    'programs' => 'البرامج',
    'projects' => 'المشاريع',
    'publications' => 'الإصدارات',
    'pillars' => 'المحاور الاستراتيجية',
    'media' => 'الوسائط',
    'directory_partners' => 'دليل الشركاء',
    'inquiries' => 'الاستفسارات',
    'email_templates' => 'قوالب البريد الإلكتروني',
    'locations' => 'المواقع الجغرافية',
    'interventions' => 'التدخلات',
    'activities' => 'الأنشطة',
    'distributions' => 'التوزيعات',
    'inventory' => 'المخزون',
    'volunteers' => 'سجلات المتطوعين',
    'committees' => 'سجلات المشاركة المجتمعية',
    'partners' => 'علاقات الشركاء والجهات المانحة',
    'partner_contacts' => 'جهات اتصال الشركاء',
    'grants' => 'المنح والمقترحات',
    'meal' => 'سجلات التقييم والمراقبة',
    'assessments' => 'التقييمات والبحوث',
    'reports' => 'التقارير التشغيلية',
    'documents' => 'المستندات',
    'workflows' => 'سير العمل',
    'tasks' => 'المهام',
    'collaboration' => 'المناقشات الداخلية',
    'accountability' => 'حالات المساءلة',
    'referrals' => 'الإحالات',
    'notifications' => 'الإشعارات',
    'automations' => 'قواعد الأتمتة',
    'calendar' => 'التقويم المؤسسي',
    'integrations' => 'سجل التكاملات',
    'dashboards' => 'لوحات العمليات المؤسسية',
];

$arabicLabelFor = static function (string $key) use ($arabicActions, $arabicResources): string {
    [$resource, $action] = array_pad(explode('.', $key, 2), 2, 'view');
    $actionLabel = $arabicActions[$action] ?? 'إدارة';
    $resourceLabel = $arabicResources[$resource] ?? $resource;

    return trim("{$actionLabel} {$resourceLabel}");
};

$definition = static function (
    string $key,
    string $label,
    bool $protected,
    array $supported,
    callable $moduleFor,
    callable $categoryFor,
    callable $arabicLabelFor,
): array {
    $module = $moduleFor($key);
    $category = $categoryFor($key, $module);
    $isSupported = in_array($key, $supported, true);
    $labelAr = $arabicLabelFor($key);
    $status = match (true) {
        $isSupported => 'supported',
        $category === 'internal' => 'internal',
        $category === 'workflow_reserved' => 'reserved',
        $category === 'operations', $category === 'phase5' => 'hidden',
        default => 'unsupported',
    };

    return [
        'module' => $module,
        'label' => $label,
        'label_ar' => $labelAr,
        'description' => 'Allows the user to '.lcfirst($label).'.',
        'description_ar' => "تتيح هذه الصلاحية للمستخدم: {$labelAr}.",
        'category' => $category,
        'status' => $status,
        'dashboard_supported' => $isSupported,
        'protected' => $protected,
    ];
};

/**
 * Existing key and protection flags are preserved. Metadata is derived once
 * so the code catalog has stable identifiers, bilingual labels, descriptions,
 * categories, and explicit support status.
 *
 * @var array<string, array{0: string, 1: bool}> $definitions
 */
$definitions = [
    'users.view' => ['View users', true],
    'users.create' => ['Create users', true],
    'users.update' => ['Update users', true],
    'users.delete' => ['Delete users', true],
    'users.assign_roles' => ['Assign user roles', true],
    'users.reset_password' => ['Reset user passwords', true],

    'roles.view' => ['View roles', true],
    'roles.create' => ['Create roles', true],
    'roles.update' => ['Update roles', true],
    'roles.delete' => ['Delete roles', true],
    'roles.grant_protected' => ['Grant protected permissions', true],

    'permissions.view' => ['View permissions', true],
    'permissions.create' => ['Create permissions', true],
    'permissions.update' => ['Update permissions', true],
    'permissions.delete' => ['Delete permissions', true],

    'settings.view' => ['View settings', true],
    'settings.update' => ['Update settings', true],
    'audit_logs.view' => ['View audit logs', true],
    'audit_logs.export' => ['Export audit logs', true],
    'audit_archives.view' => ['View audit archives', true],
    'audit_archives.download' => ['Download audit archives', true],
    'audit_archives.manage' => ['Manage audit archives', true],
    'audit_archives.cleanup' => ['Clean up archived audit logs', true],

    'content.view' => ['View content', false],
    'content.create' => ['Create content', false],
    'content.update' => ['Update content', false],
    'content.review' => ['Review content', false],
    'content.publish' => ['Publish content', false],
    'content.archive' => ['Archive content', false],
    'inquiries.update' => ['Update inquiry status', false],

    'news.view' => ['View news', false],
    'news.create' => ['Create news', false],
    'news.update' => ['Edit news', false],
    'news.delete' => ['Delete news', false],
    'news.publish' => ['Publish news', false],
    'news.unpublish' => ['Unpublish news', false],

    'programs.view' => ['View programs', false],
    'programs.create' => ['Create programs', false],
    'programs.update' => ['Edit programs', false],
    'programs.delete' => ['Delete programs', false],
    'programs.publish' => ['Publish programs', false],
    'programs.unpublish' => ['Unpublish programs', false],

    'projects.view' => ['View projects', false],
    'projects.create' => ['Create projects', false],
    'projects.update' => ['Edit projects', false],
    'projects.delete' => ['Delete projects', false],
    'projects.publish' => ['Publish projects', false],
    'projects.unpublish' => ['Unpublish projects', false],

    'publications.view' => ['View publications', false],
    'publications.create' => ['Create publications', false],
    'publications.update' => ['Edit publications', false],
    'publications.delete' => ['Delete publications', false],
    'publications.publish' => ['Publish publications', false],
    'publications.unpublish' => ['Unpublish publications', false],

    'pillars.view' => ['View strategic pillars', false],
    'pillars.create' => ['Create strategic pillars', false],
    'pillars.update' => ['Edit strategic pillars', false],
    'pillars.delete' => ['Delete strategic pillars', false],
    'pillars.publish' => ['Publish strategic pillars', false],

    'media.view' => ['View media library', false],
    'media.upload' => ['Upload media', false],
    'media.update' => ['Edit media', false],
    'media.delete' => ['Delete media', false],

    'directory_partners.view' => ['View partners directory', false],
    'directory_partners.create' => ['Create partners directory entries', false],
    'directory_partners.update' => ['Edit partners directory entries', false],
    'directory_partners.delete' => ['Delete partners directory entries', false],
    'directory_partners.publish' => ['Publish partners directory entries', false],
    'directory_partners.unpublish' => ['Unpublish partners directory entries', false],

    'inquiries.view' => ['View contact inquiries', false],
    'inquiries.view_all' => ['View all inquiries', false],
    'inquiries.view_assigned' => ['View assigned inquiries', false],
    'inquiries.assign' => ['Assign inquiries', false],
    'inquiries.add_internal_note' => ['Add internal inquiry notes', false],
    'inquiries.reply' => ['Reply to inquiries by email', false],

    'email_templates.view' => ['View email templates', false],
    'email_templates.create' => ['Create email templates', false],
    'email_templates.update' => ['Edit email templates', false],
    'email_templates.delete' => ['Delete email templates', false],

    'locations.view' => ['View geographic locations', false],
    'locations.create' => ['Create geographic locations', false],
    'locations.update' => ['Update geographic locations', false],
    'locations.transition' => ['Change geographic location status', false],

    'interventions.view' => ['View interventions', false],
    'interventions.create' => ['Create interventions', false],
    'interventions.update' => ['Update interventions', false],
    'interventions.transition' => ['Change intervention status', false],

    'activities.view' => ['View activities', false],
    'activities.create' => ['Create activities', false],
    'activities.update' => ['Update activities', false],
    'activities.transition' => ['Change activity status', false],

    'distributions.view' => ['View distributions', false],
    'distributions.create' => ['Create distributions', false],
    'distributions.update' => ['Update distributions', false],
    'distributions.transition' => ['Change distribution status', false],
    'distributions.reconcile' => ['Reconcile distributions', false],

    'inventory.view' => ['View inventory', false],
    'inventory.create' => ['Create inventory master data', false],
    'inventory.update' => ['Update inventory master data', false],
    'inventory.transition' => ['Change inventory status', false],
    'inventory.receive' => ['Receive stock', false],
    'inventory.issue' => ['Issue stock', false],
    'inventory.transfer' => ['Transfer stock', false],
    'inventory.adjust' => ['Adjust stock', false],
    'inventory.reverse' => ['Reverse stock movements', false],

    'volunteers.view' => ['View volunteer program records', false],
    'volunteers.create' => ['Create volunteer program records', false],
    'volunteers.update' => ['Update volunteer program records', false],
    'volunteers.transition' => ['Change volunteer record status', false],

    'committees.view' => ['View community participation records', false],
    'committees.create' => ['Create community participation records', false],
    'committees.update' => ['Update community participation records', false],
    'committees.transition' => ['Change community record status', false],

    'partners.view' => ['View partner and donor relationships', false],
    'partners.create' => ['Create partner and donor records', false],
    'partners.update' => ['Update partner and donor records', false],
    'partners.transition' => ['Change relationship status', false],

    'partner_contacts.view' => ['View restricted partner contacts', false],
    'partner_contacts.create' => ['Create restricted partner contacts', false],
    'partner_contacts.update' => ['Update restricted partner contacts', false],
    'partner_contacts.transition' => ['Change partner contact status', false],

    'grants.view' => ['View grants and proposals', false],
    'grants.create' => ['Create grants and proposals', false],
    'grants.update' => ['Update grants and proposals', false],
    'grants.transition' => ['Change grant and proposal status', false],
    'grants.submit' => ['Record proposal submission evidence', false],

    'meal.view' => ['View MEAL records', false],
    'meal.create' => ['Create MEAL records', false],
    'meal.update' => ['Update MEAL records', false],
    'meal.transition' => ['Change MEAL status', false],
    'meal.verify' => ['Verify MEAL measurements', false],

    'assessments.view' => ['View assessments and research', false],
    'assessments.create' => ['Create assessments and research', false],
    'assessments.update' => ['Update assessments and research', false],
    'assessments.transition' => ['Change assessment status', false],

    'reports.view' => ['View operational reports and dashboards', false],
    'reports.create' => ['Generate operational reports', false],
    'reports.update' => ['Update operational report records', false],
    'reports.export' => ['Export operational reports', false],

    'documents.view' => ['View operational document links', false],
    'documents.upload' => ['Attach operational documents', false],
    'documents.download' => ['Download operational documents', false],

    'workflows.view' => ['View workflows', false],
    'workflows.create' => ['Configure workflows', false],
    'workflows.update' => ['Update workflow definitions', false],
    'workflows.transition' => ['Advance workflow instances', false],

    'tasks.view' => ['View tasks', false],
    'tasks.create' => ['Create tasks', false],
    'tasks.update' => ['Update tasks', false],
    'tasks.delete' => ['Archive tasks', false],
    'tasks.assign' => ['Assign tasks', false],
    'tasks.transition' => ['Change task status', false],

    'collaboration.view' => ['View internal discussions', false],
    'collaboration.comment' => ['Add internal comments', false],
    'collaboration.mention' => ['Mention colleagues', false],
    'collaboration.moderate' => ['Moderate internal discussions', false],

    'accountability.create' => ['Submit feedback through authorized channels', false],
    'accountability.view' => ['View accountability cases', false],
    'accountability.update' => ['Update accountability cases', false],
    'accountability.assign' => ['Assign accountability cases', false],
    'accountability.view_sensitive' => ['View sensitive accountability notes', false],

    'referrals.view' => ['View referrals', false],
    'referrals.create' => ['Create non-identifying referrals', false],
    'referrals.update' => ['Update referrals', false],
    'referrals.transition' => ['Change referral status', false],

    'notifications.view' => ['View notification inbox', false],
    'notifications.preferences' => ['Manage notification preferences', false],

    'automations.view' => ['View automation rules', false],
    'automations.create' => ['Configure automation rules', false],
    'automations.update' => ['Update automation rules', false],
    'automations.execute' => ['Run or retry automation executions', false],

    'calendar.view' => ['View institutional calendar', false],

    'documents.review' => ['Review routed documents', false],
    'documents.submit_review' => ['Submit documents for technical review', false],

    'integrations.view' => ['View integration registry', false],
    'integrations.create' => ['Register integrations', false],
    'integrations.update' => ['Update integration configuration', false],
    'integrations.retry' => ['Retry webhook deliveries', false],

    'dashboards.view' => ['View institutional operations dashboard', false],
];

$catalog = [];

foreach ($definitions as $key => [$label, $protected]) {
    $catalog[$key] = $definition($key, $label, $protected, $supported, $moduleFor, $categoryFor, $arabicLabelFor);
}

return $catalog;
