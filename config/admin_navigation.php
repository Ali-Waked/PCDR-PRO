<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Administrative navigation contract
|--------------------------------------------------------------------------
|
| Navigation visibility is resolved on the server from RbacService. The Vue
| shell renders this list only; it never decides which capability is allowed.
| The optional `feature` field keeps each group tied to the existing registry
| in config/dashboard_features.php after the information-architecture change.
| Institutional approval entries are intentionally absent.
|
*/

return [
    'groups' => [
        [
            'key' => 'overview',
            'label' => 'overview',
            'feature' => 'overview',
            'items' => [
                ['key' => 'dashboard', 'label' => 'dashboard', 'path' => '/dashboard', 'permission' => null],
            ],
        ],
        [
            'key' => 'content',
            'label' => 'content_website',
            'feature' => 'content',
            'items' => [
                ['key' => 'cms_dashboard', 'label' => 'cms_dashboard', 'path' => '/cms', 'permission' => 'pillars.view'],
                ['key' => 'homepage', 'label' => 'homepage', 'path' => '/cms/homepage', 'permission' => 'homepage.view'],
                ['key' => 'pillars', 'label' => 'pillars', 'path' => '/cms/pillars', 'permission' => 'pillars.view'],
                ['key' => 'programs', 'label' => 'programs', 'path' => '/cms/programs', 'permission' => 'programs.view'],
                ['key' => 'projects', 'label' => 'projects', 'path' => '/cms/projects', 'permission' => 'programs.view'],
                ['key' => 'news', 'label' => 'news', 'path' => '/cms/news', 'permission' => 'news.view'],
                ['key' => 'publications', 'label' => 'publications', 'path' => '/cms/publications', 'permission' => 'publications.view'],
                ['key' => 'partner_directory', 'label' => 'partner_directory', 'path' => '/cms/partner-directory', 'permission' => 'directory_partners.view'],
                ['key' => 'media', 'label' => 'media', 'path' => '/cms/media', 'permission' => 'media.view'],
                ['key' => 'cms_settings', 'label' => 'cms_settings', 'path' => '/cms/settings', 'permission' => 'settings.view'],
            ],
        ],
        [
            'key' => 'communications',
            'label' => 'communications',
            'feature' => 'engagement',
            'items' => [
                ['key' => 'inquiries', 'label' => 'inquiries', 'path' => '/cms/inquiries', 'permission' => 'inquiries.view'],
                ['key' => 'email_templates', 'label' => 'email_templates', 'path' => '/cms/email-templates', 'permission' => 'email_templates.view'],
            ],
        ],
        [
            'key' => 'identity',
            'label' => 'identity_security',
            'feature' => 'access',
            'items' => [
                ['key' => 'roles', 'label' => 'roles_permissions', 'path' => '/admin/access', 'permission' => 'roles.view'],
                ['key' => 'users', 'label' => 'users', 'path' => '/admin/users', 'permission' => 'users.view'],
                ['key' => 'audit_logs', 'label' => 'audit_logs', 'path' => '/admin/audit-logs', 'permission' => 'audit_logs.view'],
                ['key' => 'audit_archives', 'label' => 'audit_archives', 'path' => '/admin/audit-archives', 'permission' => 'audit_archives.view'],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'system',
            'feature' => 'access',
            'items' => [
                ['key' => 'platform_settings', 'label' => 'platform_settings', 'path' => '/admin/settings', 'permission' => 'settings.view'],
            ],
        ],
    ],
];
