<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\EmailTemplate;
use App\Models\Media;
use App\Models\PartnershipInquiry;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Role;
use App\Models\StrategicPillar;
use App\Models\User;
use App\Models\VolunteerInquiry;
use App\Models\WebsiteSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class AdminDashboardService
{
    /** @var list<string> */
    private const QUICK_ACTION_KEYS = [
        'news', 'programs', 'projects', 'publications', 'media',
        'inquiries', 'email_templates', 'cms_settings',
    ];

    public function __construct(
        private readonly RbacService $rbac,
        private readonly CmsAccess $cms,
        private readonly AdminNavigationService $navigation,
        private readonly SiteSettings $siteSettings,
        private readonly AccountSessionService $accountSessions,
    ) {}

    /**
     * Build the permission-filtered operational overview for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, string $locale): array
    {
        $permissions = $this->permissions($user);
        $content = $this->contentRows($permissions);
        $pending = $this->pendingRows($permissions);

        $hour = (int) now((string) config('app.timezone'))->format('H');
        $greetingPart = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');

        return [
            'permissions' => $permissions,
            'greeting' => __('ui.admin_dashboard.greeting_'.$greetingPart, [], $locale),
            'description' => __('ui.admin_dashboard.welcome_description', [], $locale),
            'generated_at' => now()->toIso8601String(),
            'timezone' => (string) config('app.timezone'),
            'kpis' => $this->kpis($permissions, $content, $pending),
            'content' => $content,
            'pending' => $pending,
            'activity' => $this->activity($permissions, $locale),
            'account_activity' => $this->accountActivity($user, $locale),
            'quick_actions' => $this->quickActions($user, $locale),
            'website' => $this->websiteStatus($permissions, $locale),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(User $user): array
    {
        return [
            'news' => $this->cms->allows($user, 'news.view'),
            'programs' => $this->cms->allows($user, 'programs.view'),
            'projects' => $this->cms->allows($user, 'programs.view'),
            'publications' => $this->cms->allows($user, 'publications.view'),
            'pillars' => $this->cms->allows($user, 'pillars.view'),
            'media' => $this->cms->allows($user, 'media.view'),
            'inquiries' => $this->cms->allows($user, 'inquiries.view'),
            'email_templates' => $this->cms->allows($user, 'email_templates.view'),
            'settings' => $this->cms->allows($user, 'settings.view'),
            'users' => $this->rbac->has($user, 'users.view'),
            'roles' => $this->rbac->has($user, 'roles.view'),
            'permissions' => $this->rbac->has($user, 'permissions.view'),
            'audit_logs' => $this->rbac->has($user, 'audit_logs.view'),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array{key: string, href: string, metrics: list<array{key: string, value: int}>, updated_at: ?string}>
     */
    private function contentRows(array $permissions): array
    {
        $rows = [];

        if ($permissions['news']) {
            $rows[] = $this->contentRow(
                'news',
                '/cms/news',
                Article::query(),
                ContentStatus::Published->value,
                ContentStatus::Draft->value,
            );
        }

        if ($permissions['programs']) {
            $rows[] = $this->contentRow(
                'programs',
                '/cms/programs',
                Program::query(),
                ContentStatus::Published->value,
                ContentStatus::Draft->value,
            );
        }

        if ($permissions['projects']) {
            $rows[] = $this->contentRow(
                'projects',
                '/cms/projects',
                Project::query(),
                ContentStatus::Published->value,
                ContentStatus::Draft->value,
                'workflow_status',
            );
        }

        if ($permissions['publications']) {
            $rows[] = $this->contentRow(
                'publications',
                '/cms/publications',
                Publication::query(),
                ContentStatus::Published->value,
                ContentStatus::Draft->value,
            );
        }

        if ($permissions['pillars']) {
            $rows[] = $this->contentRow(
                'pillars',
                '/cms/pillars',
                StrategicPillar::query(),
                ContentStatus::Published->value,
                ContentStatus::Draft->value,
            );
        }

        if ($permissions['media']) {
            $rows[] = [
                'key' => 'media',
                'href' => '/cms/media',
                'metrics' => [
                    ['key' => 'total', 'value' => Media::query()->count()],
                    ['key' => 'public', 'value' => Media::query()->public()->count()],
                    ['key' => 'restricted', 'value' => Media::query()->restricted()->count()],
                ],
                'updated_at' => $this->latestTimestamp(Media::query()),
            ];
        }

        return $rows;
    }

    /**
     * @param  Builder<Model>  $query
     * @return array{key: string, href: string, metrics: list<array{key: string, value: int}>, updated_at: ?string}
     */
    private function contentRow(
        string $key,
        string $href,
        Builder $query,
        string $publishedStatus,
        string $draftStatus,
        string $statusColumn = 'status',
    ): array {
        $publishedQuery = (clone $query)
            ->where($statusColumn, $publishedStatus)
            ->where(function (Builder $nested): void {
                $nested->whereNull('published_at')->orWhere('published_at', '<=', now());
            });

        return [
            'key' => $key,
            'href' => $href,
            'metrics' => [
                ['key' => 'total', 'value' => (clone $query)->count()],
                ['key' => 'published', 'value' => $publishedQuery->count()],
                ['key' => 'draft', 'value' => (clone $query)->where($statusColumn, $draftStatus)->count()],
            ],
            'updated_at' => $this->latestTimestamp($query),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function latestTimestamp(Builder $query): ?string
    {
        $value = (clone $query)->max('updated_at');

        return $value === null ? null : Carbon::parse((string) $value)->toIso8601String();
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array{key: string, value: int, icon: string, href: string}>
     */
    private function kpis(array $permissions, array $content, array $pending): array
    {
        $kpis = [];
        $add = static function (string $key, int $value, string $icon, string $href) use (&$kpis): void {
            $kpis[] = ['key' => $key, 'value' => $value, 'icon' => $icon, 'href' => $href];
        };

        if ($permissions['news']) {
            $add('published_news', $this->metric($content, 'news', 'published'), 'newspaper', '/cms/news');
        }

        if ($permissions['programs']) {
            $add('active_programs', $this->metric($content, 'programs', 'published'), 'layers', '/cms/programs');
        }

        if ($permissions['projects']) {
            $add('active_projects', Project::query()->where('status', ProjectStatus::Ongoing->value)->count(), 'panel', '/cms/projects');
        }

        if ($permissions['publications']) {
            $add('publications', $this->metric($content, 'publications', 'published'), 'folder', '/cms/publications');
        }

        if ($permissions['media']) {
            $add('public_media', $this->metric($content, 'media', 'public'), 'image', '/cms/media');
        }

        if ($permissions['inquiries']) {
            $add('new_inquiries', $this->pendingValue($pending, 'inquiries'), 'mail', '/cms/inquiries');
        }

        if ($permissions['news'] || $permissions['programs'] || $permissions['publications']) {
            $add('draft_content', $this->sumRows($content, 'draft'), 'file', '/cms');
        }

        return $kpis;
    }

    /**
     * @param  list<array{key: string, metrics: list<array{key: string, value: int}>}>  $rows
     */
    private function metric(array $rows, string $key, string $metricKey): int
    {
        foreach ($rows as $row) {
            if ($row['key'] !== $key) {
                continue;
            }

            foreach ($row['metrics'] as $metric) {
                if ($metric['key'] === $metricKey) {
                    return (int) $metric['value'];
                }
            }
        }

        return 0;
    }

    /**
     * @param  list<array{key: string, value: int}>  $rows
     */
    private function pendingValue(array $rows, string $key): int
    {
        foreach ($rows as $row) {
            if ($row['key'] === $key) {
                return (int) $row['value'];
            }
        }

        return 0;
    }

    /**
     * @param  list<array{metrics: list<array{key: string, value: int}>}>  $rows
     */
    private function sumRows(array $rows, string $metricKey): int
    {
        return (int) collect($rows)
            ->flatMap(fn (array $row): array => $row['metrics'])
            ->where('key', $metricKey)
            ->sum('value');
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array{key: string, value: int, href: string, icon: string}>
     */
    private function pendingRows(array $permissions): array
    {
        $rows = [];

        if ($permissions['inquiries']) {
            $rows[] = [
                'key' => 'inquiries',
                'value' => ContactMessage::query()->where('status', 'new')->count()
                    + VolunteerInquiry::query()->where('status', 'new')->count()
                    + PartnershipInquiry::query()->where('status', 'new')->count(),
                'href' => '/cms/inquiries',
                'icon' => 'mail',
            ];
        }

        $draftCount = 0;
        if ($permissions['news']) {
            $draftCount += Article::query()->where('status', ContentStatus::Draft->value)->count();
        }
        if ($permissions['programs']) {
            $draftCount += Program::query()->where('status', ContentStatus::Draft->value)->count();
        }
        if ($permissions['publications']) {
            $draftCount += Publication::query()->where('status', ContentStatus::Draft->value)->count();
        }
        if ($permissions['projects']) {
            $draftCount += Project::query()->where('workflow_status', ContentStatus::Draft->value)->count();
        }

        if ($draftCount > 0 || $permissions['news'] || $permissions['programs'] || $permissions['publications'] || $permissions['projects']) {
            $rows[] = [
                'key' => 'draft_content',
                'value' => $draftCount,
                'href' => '/cms',
                'icon' => 'file',
            ];
        }

        return $rows;
    }

    /**
     * Compatibility-only aggregate shape retained for existing Inertia tests
     * and integrations. The redesigned UI reads `overview` instead; operational
     * and Phase 5 counters are deliberately fixed at zero so no hidden module
     * is implied.
     *
     * @return array<string, int>
     */
    public function legacyStatistics(User $user): array
    {
        $permissions = $this->permissions($user);
        $content = $this->contentRows($permissions);

        return [
            'users' => $permissions['users'] ? User::query()->count() : 0,
            'active_users' => $permissions['users'] ? User::query()->where('status', User::STATUS_ACTIVE)->count() : 0,
            'roles' => $permissions['roles'] ? Role::query()->count() : 0,
            'permissions' => $permissions['permissions'] ? Permission::query()->where('is_retired', false)->count() : 0,
            'recent_events' => $permissions['audit_logs'] ? AuditLog::query()->count() : 0,
            'content_items' => $this->sumRows($content, 'total'),
            'inquiries' => $permissions['inquiries']
                ? ContactMessage::query()->count() + VolunteerInquiry::query()->count() + PartnershipInquiry::query()->count()
                : 0,
            'email_templates' => $permissions['email_templates'] ? EmailTemplate::query()->count() : 0,
            'media_items' => $permissions['media'] ? Media::query()->count() : 0,
            'operational_records' => 0,
            'phase5_tasks' => 0,
            'phase5_workflows' => 0,
        ];
    }

    /** @return array{action: string, created_at: ?string}|null */
    public function latestAuditEvent(User $user): ?array
    {
        if (! $this->permissions($user)['audit_logs']) {
            return null;
        }

        $log = AuditLog::query()->latest('created_at')->first();

        if ($log === null) {
            return null;
        }

        return [
            'action' => (string) $log->action,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array{id: int, action: string, label: string, created_at: ?string}>
     */
    private function activity(array $permissions, string $locale): array
    {
        if (! $permissions['audit_logs']) {
            return [];
        }

        $fallback = __('ui.admin_dashboard.activity_other', [], $locale);

        return AuditLog::query()
            ->where('action', 'like', 'cms.%')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(static function (AuditLog $log) use ($fallback, $locale): array {
                $translationKey = 'ui.admin_dashboard.activity_actions.'.Str::replace(['.'], '_', $log->action);
                $label = __($translationKey, [], $locale);

                return [
                    'id' => (int) $log->id,
                    'action' => (string) $log->action,
                    'label' => $label === $translationKey ? $fallback : $label,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })->all();
    }

    /**
     * Build a permission-independent view of the authenticated user's own
     * account activity, sessions and security state.
     *
     * @return array{
     *     last_activity_at: ?string,
     *     last_login_at: ?string,
     *     active_sessions: int,
     *     current_session: bool,
     *     security_status: 'protected'|'review',
     *     events: list<array{id: int, action: string, label: string, created_at: ?string}>,
     *     profile_url: string,
     *     security_url: string
     * }
     */
    private function accountActivity(User $user, string $locale): array
    {
        $activeSessions = $this->accountSessions->count($user);
        $fallback = __('ui.admin_dashboard.account_activity_other', [], $locale);

        $events = AuditLog::query()
            ->select(['id', 'action', 'created_at'])
            ->where(function (Builder $query) use ($user): void {
                $query->where('actor_id', $user->id)
                    ->orWhere(function (Builder $nested) use ($user): void {
                        $nested->where('action', 'auth.login_failed')
                            ->where('subject_type', 'users')
                            ->where('subject_id', (string) $user->id);
                    });
            })
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(static function (AuditLog $log) use ($fallback, $locale): array {
                $translationKey = 'ui.admin_dashboard.account_activity_actions.'.Str::replace(['.'], '_', (string) $log->action);
                $label = __($translationKey, [], $locale);

                return [
                    'id' => (int) $log->id,
                    'action' => (string) $log->action,
                    'label' => $label === $translationKey ? $fallback : $label,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })->all();

        $profileUrl = route('account.profile.show', ['locale' => $locale], false);

        return [
            'last_activity_at' => $user->last_activity_at === null
                ? null
                : Carbon::parse((string) $user->last_activity_at)->toIso8601String(),
            'last_login_at' => $user->last_login_at === null
                ? null
                : Carbon::parse((string) $user->last_login_at)->toIso8601String(),
            'active_sessions' => $activeSessions,
            'current_session' => $activeSessions > 0,
            'security_status' => $user->hasVerifiedEmail() && $user->isActive() ? 'protected' : 'review',
            'events' => $events,
            'profile_url' => $profileUrl,
            'security_url' => $profileUrl.'#security',
        ];
    }

    /**
     * @return list<array{key: string, label: string, path: string, permission: ?string}>
     */
    private function quickActions(User $user, string $locale): array
    {
        return collect($this->navigation->visibleFor($user, $locale))
            ->flatMap(fn (array $group): array => $group['items'])
            ->filter(fn (array $item): bool => in_array($item['key'], self::QUICK_ACTION_KEYS, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, bool|int>|null
     */
    private function websiteStatus(array $permissions, string $locale): ?array
    {
        if (! $permissions['settings']) {
            return null;
        }

        $values = $this->siteSettings->publicValues($locale);

        return [
            'name_configured' => filled($values['organization.name'] ?? null),
            'logo_configured' => filled($values['organization.logo'] ?? null),
            'description_configured' => filled($values['seo.default_description'] ?? null),
            'contact_configured' => filled($values['contact.email'] ?? null),
            'saved_settings' => WebsiteSetting::query()->public()->count(),
        ];
    }
}
