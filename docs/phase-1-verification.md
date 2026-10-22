# Phase 1 verification

Verified locally on 22 October 2026 using the repository's Compose stack:

| Check | Result |
| --- | --- |
| Docker PHP 8.4 image build | Passed |
| PostgreSQL 17 migrations | All 5 migrations applied to a new local database |
| Pest on separate PostgreSQL test database | 42 tests passed, 124 assertions |
| Laravel Pint | Passed |
| Larastan, level 7 | Passed without a baseline or error ignores |
| Node 22 / Vite production build | Passed |
| Redis 7.4 cache write/read/delete | Passed |
| Desktop browser | Sign-in, dashboard, account navigation, logout passed |
| Mobile browser, 390px width | Sidebar opens; content fits without horizontal overflow |
| Browser console during smoke check | No errors or warnings observed |

The temporary UI smoke-test account was removed afterward. Services remain available locally on port 8000; use `docker compose down` to release their RAM without deleting volumes.

The basic GitHub Actions workflow is included but has not been executed on GitHub as part of this local implementation. PHP dependencies are resolved with Composer's PHP 8.3 platform setting; the local executable checks used Docker PHP 8.4. CI targets PHP 8.3.

No production database, legacy dump, SMTP provider, or package-owned production schema was supplied. The known-schema upgrade regression passed; production import compatibility and real external email delivery remain unverified. See `legacy-data.md` for the required migration inventory and rehearsal.
