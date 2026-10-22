# Simple Admin — Phase 1

A Laravel 13 modular monolith built from the [official Livewire starter kit](https://github.com/laravel/livewire-starter-kit). PHP 8.3+ (Docker uses 8.4), Livewire 4, Flux UI, Tailwind CSS 4, Vite, PostgreSQL 17, and Redis 7.4.

Phase 1 replaces the Laravel 5.6 / jamesmills admin / Bootstrap / Vue 2 / Mix foundation. Git history and the original user migration identities remain intact. Organization management and role/permission management are intentionally deferred. **Any registered, verified account can access the placeholder dashboard; this is not a privileged administration authorization system.**

## Included

- Fortify session authentication: registration, sign-in/out, password reset, email verification, and login throttling.
- Starter-kit account/profile, password confirmation/change, account deletion, and appearance settings.
- Responsive sidebar, mobile navigation, account menu, and dashboard placeholder.
- PostgreSQL persistence, database sessions, Redis cache and queue connection.
- Pest with starter-kit authentication/Livewire tests, Pint, Larastan level 7, and basic GitHub Actions checks.
- No seeded accounts or default application passwords. Registration creates an unverified user.

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
docker compose up -d app
```

Open [localhost:8000](http://localhost:8000). `APP_PORT` changes the host port; update `APP_URL` to match. PostgreSQL and Redis are internal services without published host ports. The PHP development server and bind mounts are for local use.

Register through the interface. The default `MAIL_MAILER=log` writes verification and reset links to `storage/logs/laravel.log`. Use the link to verify your address before entering the dashboard. For real email delivery configure the mail environment variables with your SMTP provider; log mail is not delivered externally. Keep `.env`, application keys, passwords, and logged links private.

```bash
# Watch local mail links / application output
docker compose logs -f app
# Log mail is in this file, not necessarily the container stdout:
tail -f storage/logs/laravel.log

# Stop services and release their RAM; persisted data remains.
docker compose down
# Start again without reinstalling:
docker compose up -d app
```

Do not use `docker compose down -v` unless you deliberately want to delete this project's local database/cache volumes. Keep your existing APP_KEY; rotating it invalidates encrypted sessions.

For frontend development run `docker compose run --rm --no-deps node npm run build` after changes, or use Node 22.12+ locally with `npm run dev`. This setup does not publish a container Vite development server. Redis queue support is configured; no business background jobs or permanent worker service are included in Phase 1. To process future jobs locally: `docker compose run --rm app php artisan queue:work`.

## Verification

Tests use a **separate PostgreSQL database**, `simple_admin_testing`, forced in `phpunit.xml`. Never point tests at production infrastructure. PostgreSQL's initialization script creates the test database on a new local volume. If the volume predates this configuration, create it once:

```bash
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" simple_admin_testing'
```

The [local verification record](docs/phase-1-verification.md) lists completed checks and remaining limitations. Run the same checks used by CI:

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

Pest runs both Pest scenarios and the upstream starter-kit PHPUnit-style tests. Coverage includes registration, verification (including invalid signatures/hashes), password reset, sign-in/out, profile/password changes, Livewire validation, dashboard access, throttling, removed legacy endpoints, and preservation of legacy users/password hashes during the additive auth migration. The Redis check writes and removes a unique short-lived cache key. CI runs PHP 8.3 with PostgreSQL and Redis, migrations, all PHP checks, and the frontend build. The Docker runtime uses PHP 8.4; Composer resolves dependencies against PHP 8.3 to retain that minimum version.

## Architecture and decisions

- One Laravel application and database. Authentication/account behavior is the first cohesive module; there are no microservices or speculative domain layers.
- `app/Actions/Fortify`: starter-kit user creation and password reset actions.
- `app/Models/User.php`: authenticated identity, hashing casts, verification contract.
- `app/Providers/FortifyServiceProvider.php`: authentication views, actions, responses, and rate limits.
- `resources/views/pages`: Livewire account components and authentication views; `layouts` and `components` hold shared presentation.
- `routes/web.php` and `routes/settings.php`: public/authenticated routes. Dashboard access requires `auth` and `verified`.
- `database/migrations`: retained legacy baselines plus additive infrastructure/authentication changes.
- `tests/Feature`: HTTP, Livewire, and upgrade behavior; factories provide isolated users.

The official starter source was imported from commit `0f62a26c4e4b401c1300930f47d72a497add8cce`. Registration, email verification, and password confirmation were selected with its installer. Optional passkeys and 2FA were excluded from Phase 1; Fortify may retain their transitive dependencies. Installer-only tooling was removed. Plain Vite replaces upstream Vite Plus; system fonts avoid remote font downloads during builds. Dependency lockfiles are committed. Existing user IDs remain 32-bit to avoid silently changing unknown legacy foreign keys.

Use Laravel-native validation, policies when domain resources arrive, transactions for multi-write behavior, and actions only where business behavior warrants them. Do not add repositories or service layers around ordinary Eloquent queries. Local/test lazy-loading prevention catches accidental N+1 queries. Production destructive Artisan commands are prohibited by the starter-kit provider.

## Existing data is not automatically migrated

Read [the legacy data migration limitations](docs/legacy-data.md) **before using an existing database**. This is a new application foundation, not a verified in-place production upgrade or a MySQL-to-PostgreSQL importer. No production database was accessed or modified. The local setup creates a new, namespaced PostgreSQL volume.

## Scope after Phase 1

The dashboard contains placeholders. Organizations, RBAC, user administration, business CRUD, analytics, production deployment, and legacy data import are future work. A verified login alone must not be treated as permission to administer business data when those modules are introduced.
