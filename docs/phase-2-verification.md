# Phase 2 verification

Verified locally on 22 October 2026 with the existing PHP 8.4 / PostgreSQL 17 / Redis 7.4 Compose stack:

| Check | Result |
| --- | --- |
| Additive tenancy migration | Applied successfully; original users/auth tables retained |
| Pest on dedicated PostgreSQL test database | 187 tests passed, 429 assertions |
| Permission coverage | All 50 role/permission decisions and 25 role/page combinations tested |
| PostgreSQL concurrent-owner regression | Independent remove/suspend workers block on organization locks; one change succeeds and an active Owner remains |
| SQL constraints | Cross-tenant assignments, duplicate membership/role/assignment identities, invalid states, and direct user deletion rejected |
| Laravel Pint | Passed |
| Larastan level 7 | Passed without a baseline or broad error ignores |
| Vite production build | Passed |
| Browser | Login → picker → Owner workspace → add member → switch into Viewer workspace → direct membership-page access denied with 403 |
| Responsive membership screen | Content fits the mobile viewport without horizontal overflow |

The browser smoke check used temporary local accounts and organizations, removed afterward. No production data was accessed. GitHub Actions was not executed remotely; the existing workflow will run the expanded suite when published. External SMTP delivery and legacy production import remain unverified and outside Phase 2.

The fixed role/permission catalogs are intentionally read-only. No audit workflows, custom-role builder, invitations, advanced account-management module, or organization suspension/deletion workflow was added. Current schema access-state handling is covered by the tests; direct privileged SQL changes remain outside the application's owner-count guarantee.
