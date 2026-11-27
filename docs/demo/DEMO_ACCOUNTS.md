# Preview accounts

These accounts are created by `database/seeders/Demo/DemoUsersSeeder.php` when the dedicated demo seeder runs. They are **development/testing credentials only**. They must never be created in production, reused by real staff, or given real institutional authority. All addresses use the reserved `.test` domain and do not deliver mail.

The seeder assigns only CMS roles that already exist from `CmsRolesSeeder` (`website_administrator`, `content_editor`, `publisher_reviewer`, `contact_manager`, `contact_support`). It does not create permissions, role definitions, approval authority or new gates. One account has the existing `is_platform_owner` flag for testing owner-only screens. One account is suspended to exercise inactive-user flows; its role assignment is expired.

## Accounts

| Purpose | Name | Email | Password | Locale | Status | Role keys |
| --- | --- | --- | --- | --- | --- | --- |
| Platform owner | Layla Nasser | `layla.nasser@pcdr.test` | `Owner!Preview2026#Q7k` | ar | active | `website_administrator` (plus `is_platform_owner`) |
| Website administrator | Sami Haddad | `sami.haddad@pcdr.test` | `Admin!Preview2026#M4p` | ar | active | `website_administrator` |
| Content editor | Hana Rafiq | `hana.rafiq@pcdr.test` | `Editor!Preview2026#T8v` | ar | active | `content_editor` |
| Publisher/reviewer | Karim Zuhair | `karim.zuhair@pcdr.test` | `Publish!Preview2026#W2b` | en | active | `publisher_reviewer` |
| Contact manager | Nadia Qamar | `nadia.qamar@pcdr.test` | `Contact!Preview2026#J6s` | ar | active | `contact_manager` |
| Contact support | Omar Faisal | `omar.faisal@pcdr.test` | `Support!Preview2026#L9d` | en | active | `contact_support` |
| Complaints officer | Reem Adwan | `reem.adwan@pcdr.test` | `Complaints!Preview2026#R3n` | ar | active | `contact_manager`, `contact_support` |
| Suspended reviewer | Tareq Shukri | `tareq.shukri@pcdr.test` | `Suspended!Preview2026#B5x` | en | suspended | `content_editor` (expired assignment) |

## Behavior notes

- Passwords are hashed with `Hash::make()` when inserted. Plaintext values exist only in the seeder source and this development document.
- Email addresses are tracked in the private ownership manifest as `key:users:<email>` aliases, so other seeders resolve real demo user IDs without hard-coded numeric IDs.
- The eight users and nine role assignments are owned records. Cleanup deletes them and invalidates the RBAC cache.
- `role_scopes` receives six tracked scope rows using only `all`, `self` and `assigned`, the three types understood by `App\Services\ScopeResolver`. Scope hashes are produced by `App\Support\ScopeValue`.
- Existing, untracked users are never adopted as demo-owned, even if their email matches a demo address. Re-running the seeder reuses only manifest-tracked IDs.
- Do not change these credentials for a deployment. For any non-local environment that is not a preview, leave both preview flags unset and do not run the demo seeder.
