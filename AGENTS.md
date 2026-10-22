# Working on Simple Admin

## Scope and architecture

This is a Laravel 13 / PHP 8.3+ modular monolith built from the official Livewire 4 starter kit, with Tailwind 4, plain Vite, PostgreSQL, and Redis. Phase 1 establishes authentication and an admin shell. Organization management, RBAC, user administration, and other business modules are out of scope until explicitly requested.

Keep cohesive behavior inside the application; no microservices, generic repositories, or speculative interfaces. Reuse Laravel validation, Eloquent, policies, transactions, notifications, and queue facilities. Fortify actions belong in `app/Actions/Fortify`; account Livewire components belong in `resources/views/pages/settings`; shared UI belongs in layouts/components. Introduce domain-specific modules only as actual domain behavior arrives.

## Conventions

- Follow Pint's Laravel style and use typed signatures. Keep controllers/components focused on transport and presentation; extract meaningful business operations into actions as needed.
- Use named routes, Blade escaped output, translation helpers, and accessible labels. Preserve responsive sidebar/mobile navigation and Livewire navigation behavior.
- Authentication is session-based Fortify. Dashboard routes require `auth` and `verified`. A verified account currently has no elevated role; never infer authorization from authentication alone when adding future resources.
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

`composer check` runs Pint, Larastan level 7, and Pest. Individual commands are `composer lint:check`, `composer analyse`, and `composer test`. Format deliberate PHP changes with `composer lint`. Add tests for changed behavior and report actual failures or unavailable dependencies. Do not hide static analysis errors behind broad ignores or weaken assertions to obtain a passing run.

Docker Compose serves local development only. Basic GitHub Actions CI verifies PHP 8.3, PostgreSQL, Redis, migrations, static analysis, tests, and the frontend build. No production deployment automation is included.
