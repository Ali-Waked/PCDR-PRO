<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RbacService;
use Illuminate\Database\Seeder;

/**
 * Functional website-management roles. These are role definitions only; no
 * staff accounts are created and no role is assigned to a real officeholder.
 * Institutional approval authority is deliberately absent.
 */
final class CmsRolesSeeder extends Seeder
{
    /** @return array<string, array{name: string, description: string, permissions: list<string>}> */
    private function definitions(): array
    {
        $view = [
            'news.view', 'programs.view', 'projects.view',
            'pillars.view', 'publications.view', 'media.view',
            'directory_partners.view',
        ];
        $editContent = [
            'news.create', 'news.update',
            'programs.create', 'programs.update', 'projects.create', 'projects.update',
            'pillars.create', 'pillars.update', 'publications.create', 'publications.update',
            'media.upload', 'media.update',
            'directory_partners.create', 'directory_partners.update',
        ];
        $publish = [
            'news.publish', 'news.unpublish',
            'programs.publish', 'programs.unpublish', 'projects.publish', 'projects.unpublish',
            'publications.publish', 'publications.unpublish', 'pillars.publish',
            'directory_partners.publish', 'directory_partners.unpublish',
        ];
        $inquiries = [
            'inquiries.view', 'inquiries.view_all', 'inquiries.assign',
            'inquiries.update', 'inquiries.add_internal_note', 'inquiries.reply',
        ];
        $templates = [
            'email_templates.view', 'email_templates.create',
            'email_templates.update', 'email_templates.delete',
        ];
        $access = [
            'users.view', 'users.create', 'users.update', 'users.assign_roles',
            'users.reset_password', 'roles.view', 'roles.create', 'roles.update',
            'permissions.view', 'audit_logs.view',
        ];

        return [
            'website_administrator' => [
                'name' => 'Website Administrator',
                'description' => 'Manages the public website CMS, its content, inquiries and authorized users.',
                'permissions' => array_values(array_unique(array_merge(
                    $view, $editContent, $publish, $inquiries, $templates, $access,
                    ['directory_partners.delete', 'media.delete',
                        'settings.view', 'settings.update'],
                ))),
            ],
            'content_editor' => [
                'name' => 'Content Editor',
                'description' => 'Creates and edits website content. Cannot publish or delete unless separately granted.',
                'permissions' => array_values(array_unique(array_merge($view, $editContent))),
            ],
            'publisher_reviewer' => [
                'name' => 'Publisher / Content Reviewer (reserved)',
                'description' => 'Reserved for review and publishing only after formal institutional authority is established. No approval power is granted.',
                'permissions' => $view,
            ],
            'contact_manager' => [
                'name' => 'Contact Manager',
                'description' => 'Views and manages all inquiries and replies using existing email templates.',
                'permissions' => array_values(array_unique(array_merge($inquiries, ['email_templates.view']))),
            ],
            'contact_support' => [
                'name' => 'Contact Support',
                'description' => 'Views assigned inquiries and replies where authorized. No website or user management.',
                'permissions' => [
                    'inquiries.view', 'inquiries.view_assigned', 'inquiries.update',
                    'inquiries.add_internal_note', 'inquiries.reply', 'email_templates.view',
                ],
            ],
        ];
    }

    public function run(RbacService $rbac): void
    {
        foreach ($this->definitions() as $key => $definition) {
            $role = Role::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            $ids = Permission::query()
                ->whereIn('key', $definition['permissions'])
                ->where('is_retired', false)
                ->pluck('id')
                ->all();

            $role->permissions()->sync($ids);
        }

        $rbac->invalidate();
    }
}
