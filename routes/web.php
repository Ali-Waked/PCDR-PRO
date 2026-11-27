<?php

declare(strict_types=1);

use App\Http\Controllers\Account\AvatarController;
use App\Http\Controllers\Account\EmailChangeController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AccessController;
use App\Http\Controllers\Admin\AuditArchiveController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Cms\ArticleController;
use App\Http\Controllers\Cms\AuthController;
use App\Http\Controllers\Cms\ComplaintController as CmsComplaintController;
use App\Http\Controllers\Cms\DashboardController;
use App\Http\Controllers\Cms\DirectoryPartnerCategoryController;
use App\Http\Controllers\Cms\DirectoryPartnerController;
use App\Http\Controllers\Cms\EmailTemplateController;
use App\Http\Controllers\Cms\InquiryController;
use App\Http\Controllers\Cms\MediaController as CmsMediaController;
use App\Http\Controllers\Cms\PillarController as CmsPillarController;
use App\Http\Controllers\Cms\PreviewController;
use App\Http\Controllers\Cms\ProgramController as CmsProgramController;
use App\Http\Controllers\Cms\ProjectController as CmsProjectController;
use App\Http\Controllers\Cms\PublicationController as CmsPublicationController;
use App\Http\Controllers\Cms\SectionContentController;
use App\Http\Controllers\Cms\SettingController;
use App\Http\Controllers\Cms\StructureController;
use App\Http\Controllers\Phase5\WebhookController;
use App\Http\Controllers\Public\AboutChildController;
use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\ComplaintController as PublicComplaintController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\HomepageApiController;
use App\Http\Controllers\Public\MediaCenterController;
use App\Http\Controllers\Public\MediaChildController;
use App\Http\Controllers\Public\MediaController;
use App\Http\Controllers\Public\PartnersController;
use App\Http\Controllers\Public\ProgramsController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Middleware\EnsureDashboardFeatureAvailable;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectToLocalizedUrl;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetWebsiteLocale;
use App\Services\SiteSettings;
use Illuminate\Support\Facades\Route;

$sectionPattern = implode('|', array_map(
    static fn (string $key): string => preg_quote($key, '/'),
    array_keys((array) config('cms_structure.sections', [])),
));

Route::get('/', function () {
    $locale = app(SiteSettings::class)->value(
        'website.default_language',
        (string) config('website.default_locale', 'ar'),
    );

    return redirect()->to('/'.(in_array($locale, (array) config('website.locales', ['ar', 'en']), true) ? $locale : 'ar'));
})->name('root');

// Legacy unprefixed authentication URLs redirect to the Arabic default.
Route::get('/login', fn () => redirect()->route('login.show', ['locale' => 'ar']));
Route::get('/forgot-password', fn () => redirect()->route('password.request', ['locale' => 'ar']));
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// First read-only public JSON surface. It is deliberately isolated from the
// localized Inertia routes and serves published homepage content only.
Route::get('/api/public/homepage', HomepageApiController::class)
    ->middleware('throttle:60,1')
    ->name('api.public.homepage');

// Signed, rate-limited inbound webhook receiver. CSRF is replaced by HMAC
// verification inside the controller and the route fails closed when webhooks
// are disabled in config/phase5.php.
Route::post('/webhooks/inbound/{integration:key}', [WebhookController::class, 'inbound'])
    ->middleware('throttle:30,1')
    ->name('phase5.webhooks.inbound');

Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->group(function () use ($sectionPattern): void {
        // Single route registration: a duplicate same-URI route overwrites the
        // method/URI match and can drop the earlier name from the route collection.
        Route::get('/', HomeController::class)->name('public.home');

        Route::get('/media/{uuid}/download', [MediaController::class, 'download'])->name('public.media.download');

        Route::get('/about', [AboutController::class, 'index'])->name('public.about');
        Route::get('/about/vision', [AboutChildController::class, 'vision'])->name('public.about.vision');
        Route::get('/about/objectives', [AboutChildController::class, 'objectives'])->name('public.about.objectives');
        Route::get('/about/achievements', [AboutChildController::class, 'achievements'])->name('public.about.achievements');
        Route::get('/about/board', [AboutChildController::class, 'board'])->name('public.about.board');
        Route::get('/programs', [ProgramsController::class, 'index'])->name('public.programs');
        Route::get('/media', [MediaCenterController::class, 'index'])->name('public.media.center');
        Route::get('/media/news', [MediaChildController::class, 'news'])->name('public.media.news');
        Route::get('/media/albums', [MediaChildController::class, 'albums'])->name('public.media.albums');
        Route::get('/media/stories', [MediaChildController::class, 'stories'])->name('public.media.stories');
        Route::get('/partners', PartnersController::class)->name('public.partners');
        Route::get('/contact', [ContactController::class, 'index'])->name('public.contact');
        Route::post('/contact', [ContactController::class, 'store'])
            ->middleware('throttle:10,5')
            ->name('public.contact.store');
        Route::get('/complaints', [PublicComplaintController::class, 'index'])->name('public.complaints');
        Route::post('/complaints', [PublicComplaintController::class, 'store'])
            ->middleware('throttle:10,5')
            ->name('public.complaints.store');

        Route::prefix('cms')->name('cms.')->group(function () use ($sectionPattern): void {
            Route::middleware('throttle:10,5')->group(function (): void {
                Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
                Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
            });
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // Every CMS route is protected by the `cms` middleware below,
            // which redirects guests to the real localized `cms.login` route.
            // Account status is enforced before permission evaluation.
            Route::middleware('account.active')->group(function () use ($sectionPattern): void {
                Route::get('/', DashboardController::class)->middleware('cms:pillars.view')->name('dashboard');

                // Homepage content is managed exclusively through the centralized
                // structure and typed section routes below. The legacy standalone
                // /cms/homepage editor has been removed; public rendering and its
                // shared services remain untouched.

                // Centralized website structure: fixed pages and sections only.
                Route::get('/content-management', [StructureController::class, 'index'])
                    ->middleware('cms:website_structure.view')->name('content-management.index');
                Route::patch('/content-management/pages/{websitePage:key}/toggle', [StructureController::class, 'togglePage'])
                    ->middleware('cms:website_structure.update')->name('content-management.pages.toggle');
                Route::patch('/content-management/sections/{homepageSection:key}/toggle', [StructureController::class, 'toggleSection'])
                    ->middleware('cms:website_structure.update')->name('content-management.sections.toggle');
                Route::post('/content-management/sections/reorder', [StructureController::class, 'reorder'])
                    ->middleware('cms:website_structure.update')->name('content-management.sections.reorder');

                // Typed content editing for predefined sections. The route
                // constraint prevents arbitrary section identifiers.
                Route::get('/content/{section}', [SectionContentController::class, 'index'])
                    ->middleware('cms:homepage.view')->name('content.index')->where('section', $sectionPattern);
                Route::post('/content/{section}', [SectionContentController::class, 'store'])
                    ->middleware('cms:homepage.update')->name('content.store')->where('section', $sectionPattern);
                Route::post('/content/{section}/reorder', [SectionContentController::class, 'reorder'])
                    ->middleware('cms:homepage.update')->name('content.reorder')->where('section', $sectionPattern);
                Route::put('/content/{section}/organization', [SectionContentController::class, 'organization'])
                    ->middleware('cms:homepage.update')->name('content.organization')->where('section', $sectionPattern);
                Route::put('/content/{section}/presentation', [SectionContentController::class, 'presentation'])
                    ->middleware('cms:homepage.update')->name('content.presentation')->where('section', $sectionPattern);
                Route::put('/content/{section}/{sectionItem}', [SectionContentController::class, 'update'])
                    ->middleware('cms:homepage.update')->name('content.update')->where('section', $sectionPattern);
                Route::delete('/content/{section}/{sectionItem}', [SectionContentController::class, 'destroy'])
                    ->middleware('cms:homepage.update')->name('content.destroy')->where('section', $sectionPattern);
                Route::patch('/content/{section}/{sectionItem}/toggle', [SectionContentController::class, 'toggle'])
                    ->middleware('cms:homepage.update')->name('content.toggle')->where('section', $sectionPattern);

                Route::middleware('cms:pillars.view')->group(function (): void {
                    Route::get('/pillars', [CmsPillarController::class, 'index'])->name('pillars.index');
                    Route::get('/pillars/create', [CmsPillarController::class, 'create'])->name('pillars.create');
                    Route::post('/pillars', [CmsPillarController::class, 'store'])->name('pillars.store');
                    Route::get('/pillars/{content}/edit', [CmsPillarController::class, 'edit'])->name('pillars.edit');
                    Route::put('/pillars/{content}', [CmsPillarController::class, 'update'])->name('pillars.update');
                    Route::delete('/pillars/{content}', [CmsPillarController::class, 'destroy'])->name('pillars.destroy');
                    Route::post('/pillars/{content}/status', [CmsPillarController::class, 'status'])->name('pillars.status');
                });

                Route::middleware('cms:programs.view')->group(function (): void {
                    Route::get('/programs', [CmsProgramController::class, 'index'])->name('programs.index');
                    Route::get('/programs/create', [CmsProgramController::class, 'create'])->name('programs.create');
                    Route::post('/programs', [CmsProgramController::class, 'store'])->name('programs.store');
                    Route::get('/programs/{content}/edit', [CmsProgramController::class, 'edit'])->name('programs.edit');
                    Route::put('/programs/{content}', [CmsProgramController::class, 'update'])->name('programs.update');
                    Route::delete('/programs/{content}', [CmsProgramController::class, 'destroy'])->name('programs.destroy');
                    Route::post('/programs/{content}/status', [CmsProgramController::class, 'status'])->name('programs.status');

                    Route::get('/projects', [CmsProjectController::class, 'index'])->name('projects.index');
                    Route::get('/projects/create', [CmsProjectController::class, 'create'])->name('projects.create');
                    Route::post('/projects', [CmsProjectController::class, 'store'])->name('projects.store');
                    Route::get('/projects/{content}/edit', [CmsProjectController::class, 'edit'])->name('projects.edit');
                    Route::put('/projects/{content}', [CmsProjectController::class, 'update'])->name('projects.update');
                    Route::delete('/projects/{content}', [CmsProjectController::class, 'destroy'])->name('projects.destroy');
                    Route::post('/projects/{content}/status', [CmsProjectController::class, 'status'])->name('projects.status');
                });

                Route::middleware('cms:news.view')->group(function (): void {
                    Route::get('/news', [ArticleController::class, 'index'])->name('news.index');
                    Route::get('/news/create', [ArticleController::class, 'create'])->name('news.create');
                    Route::post('/news', [ArticleController::class, 'store'])->name('news.store');
                    Route::get('/news/{content}/edit', [ArticleController::class, 'edit'])->name('news.edit');
                    Route::put('/news/{content}', [ArticleController::class, 'update'])->name('news.update');
                    Route::delete('/news/{content}', [ArticleController::class, 'destroy'])->name('news.destroy');
                    Route::post('/news/{content}/status', [ArticleController::class, 'status'])->name('news.status');
                });

                Route::middleware('cms:publications.view')->group(function (): void {
                    Route::get('/publications', [CmsPublicationController::class, 'index'])->name('publications.index');
                    Route::get('/publications/create', [CmsPublicationController::class, 'create'])->name('publications.create');
                    Route::post('/publications', [CmsPublicationController::class, 'store'])->name('publications.store');
                    Route::get('/publications/{content}/edit', [CmsPublicationController::class, 'edit'])->name('publications.edit');
                    Route::put('/publications/{content}', [CmsPublicationController::class, 'update'])->name('publications.update');
                    Route::delete('/publications/{content}', [CmsPublicationController::class, 'destroy'])->name('publications.destroy');
                    Route::post('/publications/{content}/status', [CmsPublicationController::class, 'status'])->name('publications.status');
                });

                Route::get('/media', [CmsMediaController::class, 'index'])->middleware('cms:media.view')->name('media.index');
                Route::post('/media', [CmsMediaController::class, 'store'])->middleware('cms:media.upload')->name('media.store');
                Route::put('/media/{media}', [CmsMediaController::class, 'update'])->middleware('cms:media.update')->name('media.update');
                Route::delete('/media/{media}', [CmsMediaController::class, 'destroy'])->middleware('cms:media.delete')->name('media.destroy');
                Route::get('/media/{media}/signed', [CmsMediaController::class, 'signed'])->middleware('cms:media.view')->name('media.signed');

                Route::prefix('partner-directory')->as('partner-directory.')->middleware('cms:directory_partners.view')->group(function (): void {
                    Route::get('/', [DirectoryPartnerController::class, 'index'])->name('index');
                    Route::get('/categories', [DirectoryPartnerCategoryController::class, 'index'])->name('categories.index');
                    Route::get('/partners/create', [DirectoryPartnerController::class, 'create'])->middleware('cms:directory_partners.create')->name('partners.create');
                    Route::post('/partners', [DirectoryPartnerController::class, 'store'])->middleware('cms:directory_partners.create')->name('partners.store');
                    Route::get('/partners/{partner}/edit', [DirectoryPartnerController::class, 'edit'])->name('partners.edit');
                    Route::put('/partners/{partner}', [DirectoryPartnerController::class, 'update'])->middleware('cms:directory_partners.update')->name('partners.update');
                    Route::delete('/partners/{partner}', [DirectoryPartnerController::class, 'destroy'])->middleware('cms:directory_partners.delete')->name('partners.destroy');
                    Route::post('/partners/{partner}/status', [DirectoryPartnerController::class, 'status'])->name('partners.status');
                    Route::post('/partners/reorder', [DirectoryPartnerController::class, 'reorder'])->middleware('cms:directory_partners.update')->name('partners.reorder');

                    Route::get('/categories/create', [DirectoryPartnerCategoryController::class, 'create'])->middleware('cms:directory_partners.create')->name('categories.create');
                    Route::post('/categories', [DirectoryPartnerCategoryController::class, 'store'])->middleware('cms:directory_partners.create')->name('categories.store');
                    Route::get('/categories/{category}/edit', [DirectoryPartnerCategoryController::class, 'edit'])->name('categories.edit');
                    Route::put('/categories/{category}', [DirectoryPartnerCategoryController::class, 'update'])->middleware('cms:directory_partners.update')->name('categories.update');
                    Route::delete('/categories/{category}', [DirectoryPartnerCategoryController::class, 'destroy'])->middleware('cms:directory_partners.delete')->name('categories.destroy');
                    Route::post('/categories/{category}/status', [DirectoryPartnerCategoryController::class, 'status'])->name('categories.status');
                    Route::post('/categories/reorder', [DirectoryPartnerCategoryController::class, 'reorder'])->middleware('cms:directory_partners.update')->name('categories.reorder');
                });

                Route::get('/settings', [SettingController::class, 'index'])->middleware('cms:settings.view')->name('settings.index');
                Route::put('/settings', [SettingController::class, 'update'])->middleware('cms:settings.update')->name('settings.update');
                Route::post('/settings/image', [SettingController::class, 'uploadImage'])->middleware('cms:settings.update')->name('settings.upload-image');

                Route::get('/preview/{resource}/{content}', PreviewController::class)->name('preview');

                Route::get('/inquiries', [InquiryController::class, 'index'])->middleware('cms:inquiries.view')->name('inquiries.index');
                Route::get('/inquiries/{type}/{inquiry}', [InquiryController::class, 'show'])
                    ->whereIn('type', ['contact', 'volunteer', 'complaint'])
                    ->whereNumber('inquiry')
                    ->middleware('cms:inquiries.view')
                    ->name('inquiries.show');
                Route::put('/inquiries/{type}/{inquiry}/status', [InquiryController::class, 'status'])
                    ->whereIn('type', ['contact', 'volunteer'])
                    ->whereNumber('inquiry')
                    ->middleware('cms:inquiries.update')
                    ->name('inquiries.status');
                Route::put('/inquiries/{type}/{inquiry}/assign', [InquiryController::class, 'assign'])
                    ->whereIn('type', ['contact', 'volunteer'])
                    ->whereNumber('inquiry')
                    ->middleware('cms:inquiries.assign')
                    ->name('inquiries.assign');
                Route::post('/inquiries/{type}/{inquiry}/notes', [InquiryController::class, 'note'])
                    ->whereIn('type', ['contact', 'volunteer'])
                    ->whereNumber('inquiry')
                    ->middleware('cms:inquiries.add_internal_note')
                    ->name('inquiries.note');
                Route::post('/inquiries/{type}/{inquiry}/reply', [InquiryController::class, 'reply'])
                    ->whereIn('type', ['contact', 'volunteer'])
                    ->whereNumber('inquiry')
                    ->middleware('cms:inquiries.reply')
                    ->name('inquiries.reply');

                // Complaints share the inquiries management surface but keep their
                // own authorization and private data boundaries.
                Route::prefix('complaints')->as('complaints.')->group(function (): void {
                    Route::put('/{complaint}/status', [CmsComplaintController::class, 'status'])
                        ->whereNumber('complaint')->middleware('cms:complaints.update')->name('status');
                    Route::put('/{complaint}/priority', [CmsComplaintController::class, 'priority'])
                        ->whereNumber('complaint')->middleware('cms:complaints.update')->name('priority');
                    Route::put('/{complaint}/assign', [CmsComplaintController::class, 'assign'])
                        ->whereNumber('complaint')->middleware('cms:complaints.assign')->name('assign');
                    Route::post('/{complaint}/notes', [CmsComplaintController::class, 'note'])
                        ->whereNumber('complaint')->middleware('cms:complaints.add_internal_note')->name('notes');
                    Route::post('/{complaint}/resolve', [CmsComplaintController::class, 'resolve'])
                        ->whereNumber('complaint')->middleware('cms:complaints.resolve')->name('resolve');
                });

                Route::middleware('cms:email_templates.view')->group(function (): void {
                    Route::get('/email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
                    Route::post('/email-templates/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
                    Route::post('/email-templates/send-test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.send-test');
                });
                Route::post('/email-templates', [EmailTemplateController::class, 'store'])->middleware('cms:email_templates.create')->name('email-templates.store');
                Route::post('/email-templates/{template}/duplicate', [EmailTemplateController::class, 'duplicate'])->middleware('cms:email_templates.create')->name('email-templates.duplicate');
                Route::patch('/email-templates/{template}/status', [EmailTemplateController::class, 'setStatus'])->middleware('cms:email_templates.update')->name('email-templates.status');
                Route::put('/email-templates/{template}', [EmailTemplateController::class, 'update'])->middleware('cms:email_templates.update')->name('email-templates.update');
                Route::delete('/email-templates/{template}', [EmailTemplateController::class, 'destroy'])->middleware('cms:email_templates.delete')->name('email-templates.destroy');
            });
        });

        // Phase 2 authentication and platform administration routes.
        Route::middleware('guest')->group(function (): void {
            Route::get('/login', [AuthenticatedSessionController::class, 'show'])->name('login.show');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:login')
                ->name('login.store');

            Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
            Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
                ->middleware('throttle:password-reset')
                ->name('password.email');
            Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
            Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.store');
        });

        Route::middleware('auth')->group(function (): void {
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
            Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
            Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:6,1')
                ->name('verification.resend');
        });

        Route::middleware(['auth', 'account.active'])->group(function (): void {
            Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

            // Self-service account security lives under the profile page's
            // #security anchor; there is no separate security page route.
            Route::get('/dashboard/profile', [ProfileController::class, 'show'])->name('account.profile.show');
            Route::put('/dashboard/profile', [ProfileController::class, 'update'])->name('account.profile.update');

            Route::get('/dashboard/profile/email/confirm/{token}', [EmailChangeController::class, 'confirm'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('account.email.change.confirm');
            Route::post('/dashboard/profile/email', [EmailChangeController::class, 'store'])
                ->middleware('throttle:account-email')
                ->name('account.email.change.request');
            Route::delete('/dashboard/profile/email', [EmailChangeController::class, 'destroy'])
                ->middleware('throttle:account-email')
                ->name('account.email.change.cancel');

            Route::post('/dashboard/profile/password', [PasswordController::class, 'update'])
                ->middleware('throttle:account-sensitive')
                ->name('account.password.update');
            Route::delete('/dashboard/profile/sessions/{reference}', [PasswordController::class, 'revokeSession'])
                ->middleware('throttle:account-sensitive')
                ->name('account.sessions.destroy');
            Route::delete('/dashboard/profile/sessions', [PasswordController::class, 'revokeOtherSessions'])
                ->middleware('throttle:account-sensitive')
                ->name('account.sessions.destroy-others');
            Route::get('/dashboard/profile/sessions', [PasswordController::class, 'sessionsJson'])
                ->middleware('throttle:60,1')
                ->name('account.sessions.index');

            Route::post('/dashboard/profile/avatar', [AvatarController::class, 'store'])
                ->middleware(['throttle:account-avatar', 'throttle:account-avatar-hour'])
                ->name('account.avatar.store');
            Route::delete('/dashboard/profile/avatar', [AvatarController::class, 'destroy'])
                ->middleware('throttle:account-sensitive')
                ->name('account.avatar.destroy');
            Route::get('/dashboard/profile/avatar/{media}/download', [AvatarController::class, 'download'])
                ->middleware(['signed', 'throttle:60,1'])
                ->name('account.avatar.download');

            Route::prefix('admin')->as('admin.')->group(function (): void {
                Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
                Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.create')->name('users.create');
                Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create')->name('users.store');
                Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view')->name('users.show');
                Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
                Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
                Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete')->name('users.destroy');
                Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->middleware('permission:users.update')->name('users.status');
                Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->middleware('permission:users.assign_roles')->name('users.roles');
                Route::post('users/{user}/reset-password', [UserController::class, 'sendPasswordReset'])->middleware(['permission:users.reset_password', 'throttle:3,1'])->name('users.reset-password');

                // One canonical Roles & Permissions page. The old role and
                // permission index URLs remain as bookmark-compatible redirects.
                Route::get('access', [AccessController::class, 'index'])->middleware('can:access.view')->name('access.index');
                Route::get('roles', [RoleController::class, 'index'])->middleware('can:access.view')->name('roles.index');
                Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
                Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
                Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('can:access.view')->name('roles.edit');
                Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
                Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete')->name('roles.destroy');
                Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:roles.update')->name('roles.permissions');

                Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.view')->name('permissions.index');

                Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view')->name('audit-logs.index');
                Route::get('audit-logs/export', [AuditLogController::class, 'export'])->middleware('permission:audit_logs.export')->name('audit-logs.export');
                Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit_logs.view')->name('audit-logs.show');

                Route::get('audit-archives', [AuditArchiveController::class, 'index'])->middleware('permission:audit_archives.view')->name('audit-archives.index');
                Route::post('audit-archives', [AuditArchiveController::class, 'store'])->middleware('permission:audit_archives.manage')->name('audit-archives.store');
                Route::get('audit-archives/{archive}', [AuditArchiveController::class, 'show'])->middleware('permission:audit_archives.view')->name('audit-archives.show');
                Route::get('audit-archives/{archive}/download', [AuditArchiveController::class, 'download'])->middleware('permission:audit_archives.download')->name('audit-archives.download');
                Route::post('audit-archives/{archive}/verify', [AuditArchiveController::class, 'verify'])->middleware('permission:audit_archives.manage')->name('audit-archives.verify');

                Route::get('settings', [AdminSettingController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
                Route::put('settings', [AdminSettingController::class, 'update'])->middleware('permission:settings.update')->name('settings.update');
            });
        });

        // Phase 4 institutional operational modules.
        require __DIR__.'/operations.php';

        // Phase 5 institutional operating layer.
        require __DIR__.'/phase5.php';

    });

// Unmatched requests may map to an existing localized page. A controller class
// keeps the fallback serializable under `route:cache`. The fallback skips the
// settings/user-data middleware so 404 and redirect responses stay independent
// of database-backed navigation, settings and Inertia shared props.
// `Route::fallback()` is GET/HEAD only; registering the same fallback route
// through `match()` lets non-GET unmatched requests render the normal 404 too.
$localizedFallback = Route::match(
    ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    '{fallbackPlaceholder}',
    RedirectToLocalizedUrl::class,
)
    ->where('fallbackPlaceholder', '.*')
    ->fallback()
    ->withoutMiddleware([
        SetLocale::class,
        SetWebsiteLocale::class,
        EnsureDashboardFeatureAvailable::class,
        HandleInertiaRequests::class,
    ]);
