# Working on Simple Admin

## Scope and architecture

This is a Laravel 13 / PHP 8.3+ modular monolith built from the official Livewire 4 starter kit, with Tailwind 4, plain Vite, PostgreSQL, and Redis. Phase 3 includes invitations, membership lifecycle, teams/departments, MFA, sessions, API tokens and English/German interfaces. Custom-role editing, audit workflows and business modules remain out of scope. Read docs/identity.md before changing identity behavior.

Keep cohesive behavior inside the application; no microservices, generic repositories, or speculative interfaces. Reuse Laravel validation, Eloquent, policies, transactions, notifications, and queue facilities. Fortify actions belong in `app/Actions/Fortify`; account Livewire components belong in `resources/views/pages/settings`; shared UI belongs in layouts/components. Introduce domain-specific modules only as actual domain behavior arrives.

## Conventions

- Follow Pint's Laravel style and use typed signatures. Keep controllers/components focused on transport and presentation; extract meaningful business operations into actions as needed.
- Use named routes, Blade escaped output, translation helpers, and accessible labels. Preserve responsive sidebar/mobile navigation and Livewire navigation behavior.
- Authentication is session-based Fortify. Dashboard routes require `auth` and `verified`. Tenant routes also require `EnsureTenantContext`, and every action must authorize through the freshly resolved active membership. Never infer authorization from authentication alone.
- Read `docs/tenancy.md` before changing access behavior. A browser-supplied organization/member/role ID is only a lookup candidate. Scope lookups through the current organization and authorize the operation. Never accept browser permission lists or serialized memberships as authority.
- Sensitive mutations must lock the organization row in a transaction, then re-resolve authorization. Keep final-active-owner checks inside that lock. Membership additions also lock the target user to coordinate with account deletion. The database forbids cross-tenant assignments with composite foreign keys.
- Role and permission catalogs are fixed and read-only. One role assignment per membership; Owner/Admin hierarchy restrictions supplement permission checks. No global super-admin Gate bypass.
- Keep tenant middleware persistent across Livewire updates and authorize public Livewire actions independently. Do not cache effective permissions across requests or retain an active tenant in a singleton.
- Account deletion uses the existing authenticated flow plus `DeleteAccount`; it must not delete users with any memberships. Preserve the membership user foreign key restriction.
- Use factories in tests; seed no default credentials. Write behavioral Pest tests for new work. Retain useful upstream authentication and Livewire tests.
- Keep migrations additive. Preserve the legacy baseline migration names and 32-bit user IDs until a separately designed data migration is approved. See `docs/legacy-data.md`.
- Never run destructive schema commands or delete volumes against existing data. Do not silently skip schema collisions or assume a MySQL database has been imported into PostgreSQL.
- Commit dependency lockfiles. Never commit `.env`, credentials, database dumps, vendor, node_modules, compiled assets, or runtime logs.
- Prefer Laravel-native capabilities and existing starter dependencies. Add packages or architecture layers only for a concrete requirement.

## Verification

Use Docker Compose when host PHP/Node versions differ. First follow the README setup, including the dedicated `simple_admin_testing` PostgreSQL database. Tests use that database and may reset its schema; never connect them to real data.

```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app composer check
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run build
docker compose run --rm app php artisan app:check-redis
```

`composer check` runs Pint, Larastan level 7, and Pest. Individual commands are `composer lint:check`, `composer analyse`, and `composer test`. Format deliberate PHP changes with `composer lint`. Add tests for changed behavior and report actual failures or unavailable dependencies. The PostgreSQL owner concurrency tests start two independent PHP processes and verify lock waits through `pg_stat_activity`; run this suite serially against the dedicated test database. Do not replace those checks with SQLite or mock locks. Do not hide static analysis errors behind broad ignores or weaken assertions to obtain a passing run.

Docker Compose serves local development only. Basic GitHub Actions CI verifies PHP 8.3, PostgreSQL, Redis, migrations, static analysis, tests, and the frontend build. No production deployment automation is included.

## Identity security conventions

- Organization administrators change tenant membership profiles/status, never another user's global identity. Resolve active, verified user and membership afresh on every operation.
- Invitation tokens are random, expiring, single-use and stored only as hashes. Queued invitation notifications must be encrypted. Recheck recipient identity and inviter authority at acceptance under locks.
- Preserve final-active-owner and team-owner guards for membership and account lifecycle changes. Bulk operations must authorize every selected target and roll back together on failure.
- Reuse Fortify MFA; serialize challenge/recovery-code consumption and MFA mutations on the user row. Sensitive account operations require password confirmation. Never expose stored secrets, raw session identifiers or token hashes.
- Sanctum tokens require an organization, explicit permitted abilities and an expiry no more than 90 days away. Bearer-only API context comes from the authenticated token, never a browser organization ID. Check current membership permission and token ability together.
- Security credential changes revoke other sessions and API tokens. Show plaintext tokens once only. Use opaque session references in the UI.
- Keep English/German catalogs aligned. Use queued security notifications; local mail defaults to log. Never commit test credentials or invitation links.
- Run PostgreSQL concurrency tests serially; they verify invitation and recovery-code single use as well as final-owner protection.
