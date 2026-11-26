# Homepage CMS management removal

## Summary

The administrative Homepage CMS editor (`/{locale}/cms/homepage`) and its exclusive backend and frontend implementation were removed. The public homepage, its CMS-backed read-only data flow, and all unrelated modules were preserved.

## Removed

### Backend

- `app/Http/Controllers/Cms/HomepageController.php`
- `app/Http/Requests/Cms/DeleteHomepageSectionRequest.php`
- `app/Http/Requests/Cms/ReorderHomepageRequest.php`
- `app/Http/Requests/Cms/StoreHomepageSectionRequest.php`
- `app/Http/Requests/Cms/ToggleHomepageSectionRequest.php`
- `app/Http/Requests/Cms/UpdateHomepageRequest.php`
- `app/Http/Requests/Cms/UpdateHomepageStatusRequest.php`
- Homepage permission definitions and grants in `config/cms.php`, `config/permissions.php`, `config/admin_navigation.php`, and `database/seeders/CmsRolesSeeder.php`
- Homepage dashboard content row and permission flag in `app/Services/AdminDashboardService.php`
- Homepage navigation icon entries in the CMS and dashboard layouts

### Frontend

- `resources/js/Pages/Cms/Homepage/Index.vue`
- ADMIN navigation entry for `/cms/homepage`
- Homepage-specific navigation icon mappings

### Routes

All CMS homepage routes were removed from `routes/web.php`:

- `GET /{locale}/cms/homepage` (`cms.homepage.index`)
- `PUT /{locale}/cms/homepage` (`cms.homepage.update`)
- `GET /{locale}/cms/homepage/preview` (`cms.homepage.preview`)
- `POST /{locale}/cms/homepage/reorder` (`cms.homepage.reorder`)
- `PATCH /{locale}/cms/homepage/{homepageSection}/status` (`cms.homepage.status`)
- `POST /{locale}/cms/homepage/{homepageSection}/toggle` (`cms.homepage.toggle`)
- `POST /{locale}/cms/homepage/sections` (`cms.homepage.sections.store`)
- `DELETE /{locale}/cms/homepage/sections/{homepageSection}` (`cms.homepage.sections.destroy`)

### Tests

- Deleted `tests/Feature/RedesignHomepageCmsTest.php`.
- Removed CMS-only test methods from `tests/Feature/RedesignHomepageApiTest.php`, `tests/Feature/HomepageBackendIntegrationTest.php`, and `tests/Feature/PrototypeHomepageTest.php`.
- Removed Homepage CMS route/permission expectations from shared CMS navigation, workflow, and public-surface tests. Public homepage assertions were left intact.

## Preserved

- Public homepage route `/{locale}` and public Inertia page `Public/Home.vue`.
- Read-only public API `GET /api/public/homepage` (`api.public.homepage`).
- `app/Services/HomepageService.php`, `app/Http/Controllers/Public/HomepageApiController.php`, public resources, and homepage section/item/translation models.
- Public homepage sections, content, RTL/LTR rendering, assets, header, footer, social links, partner directory, complaints, inquiries, and all other CMS modules.
- Shared authentication, RBAC, CMS access middleware, and settings infrastructure.

## Database decisions

No migrations were deleted or modified and no destructive database commands were executed. Homepage section tables, translations, items, and their records remain intact because the public homepage reads from them. Existing `homepage.view` / `homepage.update` rows in deployed databases are not deleted; only the application-level definitions, grants, and editor UI that used them were removed.

## Tests executed

- PHP lint (`php -l`) on all modified PHP files.
- Laravel Pint on all modified PHP files.
- `php artisan route:list --except-vendor` (no `cms/homepage` or `cms.homepage` routes; public homepage API present).
- PHPUnit feature tests: `PublicSurfacePreservationTest`, `CmsWorkflowTest`, `AdminNavigationReorganizationTest`, `CmsNavigationModuleRemovalTest`, `RedesignHomepageApiTest`, `RedesignHomepageFrontendTest`, `HomepageBackendIntegrationTest`, `PrototypeHomepageTest`.
- `npm run build` production build, followed by a check that the built bundle contains no Homepage CMS page or route.

## Known limitations

- Historical documentation under `docs/redesign`, `docs/reconstruction`, and `docs/reports` still describes the removed editor as a historical record; it was not rewritten.
- Deployed databases may still contain legacy homepage permission rows because data deletion was intentionally not performed.
- The Homepage CMS editor cannot be re-enabled without restoring the removed code from the verified backup or version history.
