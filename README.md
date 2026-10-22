# Simple Admin — Phase 3

A Laravel 13 modular monolith built from the [official Livewire starter kit](https://github.com/laravel/livewire-starter-kit). PHP 8.3+ (Docker uses 8.4), Livewire 4, Flux UI, Tailwind CSS 4, Vite, PostgreSQL 17, and Redis 7.4.

Phase 1 replaced the Laravel 5.6 / jamesmills admin / Bootstrap / Vue 2 / Mix foundation. Phase 2 adds secure organization membership and tenant-aware authorization. Git history and the original user migration identities remain intact. **Verified users must select an active organization membership before accessing its dashboard or administration screens.**

## Included

- Fortify session authentication: registration, sign-in/out, password reset, email verification, and login throttling.
- Starter-kit account/profile, password confirmation/change, account deletion, and appearance settings.
- Responsive sidebar, mobile navigation, account menu, and dashboard placeholder.
- PostgreSQL persistence, database sessions, Redis cache and queue connection.
- Pest with starter-kit authentication/Livewire tests, Pint, Larastan level 7, and basic GitHub Actions checks.
- No seeded accounts or default application passwords. Registration creates an unverified user.
- Multiple organizations per user, with server-validated organization switching.
- Organization-scoped Owner, Admin, Manager, Support, and Viewer roles, resource/action permissions, policies, and Gates.
- Livewire organization creation/rename, member addition/role changes/suspension/removal, and read-only role/permission catalogs.
- Cross-tenant foreign keys, transaction locks, final-active-owner protection, and negative authorization tests.

See [tenancy and authorization](docs/tenancy.md) for the exact permission matrix, trust boundaries, ownership rules, and scope limitations.

## Run locally with Docker Compose

Prerequisite: Docker Engine/Desktop with Compose v2. Host PHP and Node installations are unnecessary.

```bash
cp .env.example .env
# On Linux, set LOCAL_UID/LOCAL_GID in .env if your IDs differ from 1000.
docker compose build app
docker compose up -d postgres redis
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run build
docker compose up -d app worker
```

Open [localhost:8000](http://localhost:8000). `APP_PORT` changes the host port; update `APP_URL` to match. PostgreSQL and Redis are internal services without published host ports. The PHP development server and bind mounts are for local use.

Register through the interface. The default `MAIL_MAILER=log` writes verification and reset links to `storage/logs/laravel.log`. Use the link to verify your address. Sign-in and verification then lead to the organization picker. Create an organization to become its initial Owner, or ask an existing Owner/Admin to add your verified account. Choose an organization to enter its dashboard. For real email delivery configure the mail environment variables with your SMTP provider; log mail is not delivered externally. Keep `.env`, application keys, passwords, and logged links private.

```bash
# Watch local mail links / application output
docker compose logs -f app
# Log mail is in this file, not necessarily the container stdout:
tail -f storage/logs/laravel.log

# Stop services and release their RAM; persisted data remains.
docker compose down
# Start again without reinstalling:
docker compose up -d app worker
```

Do not use `docker compose down -v` unless you deliberately want to delete this project's local database/cache volumes. Keep your existing APP_KEY; rotating it invalidates encrypted sessions.

For frontend development run `docker compose run --rm --no-deps node npm run build` after changes, or use Node 22.12+ locally with `npm run dev`. This setup does not publish a container Vite development server. The worker processes queued invitations and security notifications. Restart it after changing PHP code: `docker compose restart worker`. Default mail is logged locally; configure SMTP for external delivery.

## Verification

Tests use a **separate PostgreSQL database**, `simple_admin_testing`, forced in `phpunit.xml`. Never point tests at production infrastructure. PostgreSQL's initialization script creates the test database on a new local volume. If the volume predates this configuration, create it once:

```bash
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" simple_admin_testing'
```

The [Phase 3 verification record](docs/phase-3-verification.md) lists completed checks and remaining limitations. Run the same checks used by CI:

```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app composer check
# Individual checks:
docker compose run --rm app composer test
docker compose run --rm app composer lint:check
docker compose run --rm app composer analyse
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run build
docker compose run --rm app php artisan app:check-redis
```

Pest runs both Pest scenarios and the upstream starter-kit PHPUnit-style tests. Coverage includes registration, verification (including invalid signatures/hashes), password reset, sign-in/out, profile/password changes, Livewire validation, dashboard access, throttling, removed legacy endpoints, preservation of legacy users/password hashes during the additive auth migration, tenant isolation, the full permission matrix, role-assignment hierarchy, revoked Livewire access, and simultaneous final-owner changes on independent PostgreSQL connections. The Redis check writes and removes a unique short-lived cache key. CI runs PHP 8.3 with PostgreSQL and Redis, migrations, all PHP checks, and the frontend build. The Docker runtime uses PHP 8.4; Composer resolves dependencies against PHP 8.3 to retain that minimum version.

## Architecture and decisions

- One Laravel application and database. Authentication and organization access are cohesive application modules; there are no microservices or speculative domain layers.
- `app/Actions/Fortify`: starter-kit user creation and password reset actions.
- `app/Models/User.php`: authenticated identity, hashing casts, verification contract.
- `app/Providers/FortifyServiceProvider.php`: authentication views, actions, responses, and rate limits.
- `resources/views/pages`: Livewire account components and authentication views; `layouts` and `components` hold shared presentation.
- `app/Actions/Organizations` and `app/Actions/Memberships`: transactional organization and membership operations.
- `app/Tenancy`: fresh membership resolution and final-owner invariant checks.
- `app/Policies` and `app/Enums`: server-side resource permissions and fixed roles/statuses.
- `app/Livewire`: organization/member screens and role/permission catalog presentation.
- `routes/web.php` and `routes/settings.php`: public/account routes, organization picker, and tenant routes protected by authentication, verification, tenant middleware, and authorization.
- `database/migrations`: retained legacy baselines plus additive infrastructure/authentication changes.
- `tests/Feature`: HTTP, Livewire, and upgrade behavior; factories provide isolated users.

The official starter source was imported from commit `0f62a26c4e4b401c1300930f47d72a497add8cce`. Registration, email verification, and password confirmation were selected with its installer. Optional passkeys and 2FA were excluded from Phase 1; Fortify may retain their transitive dependencies. Installer-only tooling was removed. Plain Vite replaces upstream Vite Plus; system fonts avoid remote font downloads during builds. Dependency lockfiles are committed. Existing user IDs remain 32-bit to avoid silently changing unknown legacy foreign keys.

Use Laravel-native validation, policies when domain resources arrive, transactions for multi-write behavior, and actions only where business behavior warrants them. Do not add repositories or service layers around ordinary Eloquent queries. Local/test lazy-loading prevention catches accidental N+1 queries. Production destructive Artisan commands are prohibited by the starter-kit provider.

## Existing data is not automatically migrated

Read [the legacy data migration limitations](docs/legacy-data.md) **before using an existing database**. This is a new application foundation, not a verified in-place production upgrade or a MySQL-to-PostgreSQL importer. No production database was accessed or modified. The local setup creates a new, namespaced PostgreSQL volume.

## Scope after Phase 3

The dashboard still contains business placeholders. Custom-role editing, audit workflows, organization suspension/deletion screens, business CRUD, analytics, production deployment, and legacy data import remain future work. Account self-deletion remains blocked while memberships exist.

## Identity management

Phase 3 adds expiring single-use invitations, organization-scoped membership profiles and lifecycle management, departments, teams and ownership, atomic permission-checked bulk operations, English/German interfaces, native Fortify TOTP and recovery codes, session revocation, and scoped Sanctum API tokens. Search, filters, sorting and pagination are available on management screens.

See [identity management](docs/identity.md) for security decisions, account lifecycle, API endpoints and operational instructions. Organization administrators manage memberships rather than disabling a person's identity across unrelated organizations. Global account deactivation/reactivation is self-service; global suspension is a trusted operator state.

Fortify two-factor authentication, excluded in Phase 1, is enabled in Phase 3. Passkeys remain excluded. No default accounts or passwords are provided.
