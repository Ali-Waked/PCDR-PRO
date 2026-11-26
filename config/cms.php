<?php

declare(strict_types=1);

/**
 * Phase 3 CMS contract.
 *
 * Permission keys deliberately match the names documented in
 * docs/architecture/AUTHORIZATION_ARCHITECTURE.md so that the Phase 2
 * permission catalogue can take over without renaming anything.
 */
return [
    'route_prefix' => 'cms',

    /**
     * Development-only bootstrap identity. Never used in production.
     * Phase 2's RBAC catalogue is authoritative once available.
     */
    'bootstrap' => [
        'email' => env('CMS_BOOTSTRAP_EMAIL'),
    ],

    /**
     * Temporary local sign-in shim. Phase 2 owns production authentication.
     */
    'local_auth_enabled' => env('CMS_LOCAL_AUTH', null),

    'permissions' => [
        'dashboard' => 'pillars.view',
        'pillars.view' => 'View strategic pillars',
        'pillars.update' => 'Edit strategic pillars',
        'programs.view' => 'View programmes and projects',
        'programs.update' => 'Edit programmes and projects',
        'news.view' => 'View news',
        'news.create' => 'Create news',
        'news.update' => 'Edit news',
        'news.publish' => 'Publish news',
        'publications.view' => 'View publications',
        'publications.create' => 'Create publications',
        'publications.update' => 'Edit publications',
        'publications.publish' => 'Publish publications',
        'media.view' => 'View media library',
        'media.upload' => 'Upload media',
        'media.update' => 'Edit media',
        'media.delete' => 'Delete media',
        'directory_partners.view' => 'View partners directory',
        'directory_partners.create' => 'Create partners directory entries',
        'directory_partners.update' => 'Edit partners directory entries',
        'directory_partners.delete' => 'Delete partners directory entries',
        'directory_partners.publish' => 'Publish partners directory entries',
        'directory_partners.unpublish' => 'Unpublish partners directory entries',
        'settings.view' => 'View public site settings',
        'settings.update' => 'Edit public site settings',
        'inquiries.view' => 'View inquiries',
        'inquiries.view_all' => 'View all inquiries',
        'inquiries.view_assigned' => 'View assigned inquiries',
        'inquiries.assign' => 'Assign inquiries',
        'inquiries.update' => 'Update inquiry status',
        'inquiries.add_internal_note' => 'Add internal inquiry notes',
        'inquiries.reply' => 'Reply to inquiries by email',
        'email_templates.view' => 'View email templates',
        'email_templates.create' => 'Create email templates',
        'email_templates.update' => 'Edit email templates',
        'email_templates.delete' => 'Delete email templates',

        // Phase 4 operational modules. The keys match config/permissions.php.
        'locations.view' => 'View locations',
        'interventions.view' => 'View interventions',
        'activities.view' => 'View activities',
        'distributions.view' => 'View distributions',
        'inventory.view' => 'View inventory',
        'volunteers.view' => 'View volunteer program records',
        'committees.view' => 'View community participation records',
        'partners.view' => 'View partners and donors',
        'partner_contacts.view' => 'View restricted partner contacts',
        'grants.view' => 'View grants and proposals',
        'meal.view' => 'View MEAL records',
        'assessments.view' => 'View assessments and research',
        'reports.view' => 'View operational reports and dashboards',
        'documents.view' => 'View operational documents',

        // Phase 5 institutional operating layer.
        'workflows.view' => 'View workflows',
        'tasks.view' => 'View tasks',
        'collaboration.view' => 'View internal discussions',
        'accountability.view' => 'View accountability cases',
        'referrals.view' => 'View referrals',
        'notifications.view' => 'View notification inbox',
        'automations.view' => 'View automation rules',
        'calendar.view' => 'View institutional calendar',
        'documents.review' => 'Review routed documents',
        'integrations.view' => 'View integration registry',
        'dashboards.view' => 'View operations dashboard',
    ],

    /**
     * The public workflow defined in the project brief. Institutional
     * approval is a separate governance concern and is not implied here.
     */
    'transitions' => [
        'draft' => ['pending_review', 'archived'],
        'pending_review' => ['draft', 'approved', 'archived'],
        'approved' => ['draft', 'published', 'archived'],
        'published' => ['draft', 'archived'],
        'archived' => ['draft'],
    ],

    /**
     * Project lifecycle is deliberately distinct from publication workflow.
     */
    'project_statuses' => ['planned', 'ongoing', 'completed', 'archived'],

    /**
     * Operational handling states for public enquiries. These are workflow
     * labels only and carry no institutional approval meaning.
     */
    'inquiry_statuses' => ['new', 'in_progress', 'resolved', 'closed'],

    'inquiry_status_aliases' => [
        'unread' => 'new',
        'open' => 'in_progress',
        'in-progress' => 'in_progress',
        'answered' => 'resolved',
        'reply_sent' => 'resolved',
        'rejected' => 'closed',
    ],

    'editor' => [
        'allowed_tags' => ['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'h4',
            'ul', 'ol', 'li', 'blockquote', 'a', 'hr', 'figure', 'figcaption',
            'table', 'thead', 'tbody', 'tr', 'th', 'td', 'code', 'pre',
            'mark', 'sub', 'sup', 'img'],
        'allowed_attributes' => ['href', 'src', 'alt', 'title', 'target', 'rel', 'class', 'style', 'width', 'height'],
        'removed_tags' => ['script', 'style', 'iframe', 'object', 'embed', 'svg',
            'math', 'template', 'form', 'input', 'button', 'meta', 'link', 'base'],
    ],
];
