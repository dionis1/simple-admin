# Local development and verification

The supplied [Compose configuration](../compose.yaml) runs a PHP development server, PostgreSQL, Redis and a queue worker. Use Docker Engine/Desktop with Compose v2; host PHP, Composer and Node are unnecessary for the container commands below. Run commands from the repository root. This setup is not a production deployment configuration.

## First installation

```bash
cp .env.example .env
docker compose build app
docker compose up -d postgres redis
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run build
docker compose up -d app worker
```

On Linux, the app, worker and Node containers use UID/GID `1000:1000` by default. Before installation, run `id -u` and `id -g`. If your values differ, add matching `LOCAL_UID` and `LOCAL_GID` values to `.env` so files written through the bind mount belong to your user.

Open [http://localhost:8000](http://localhost:8000). To use a different port, add `APP_PORT` to `.env` and update `APP_URL` to match. The app binds to the host loopback address. PostgreSQL and Redis have no published host ports.

Only copy `.env` and generate `APP_KEY` for a new installation. Keep an existing key: encrypted sessions, MFA secrets and queued notifications depend on it. Do not commit `.env`, credentials, runtime logs, database dumps or private links. Before using legacy data, read [legacy-data.md](legacy-data.md).

## Register and enter an organization

1. Register through the interface; the application seeds no default accounts or passwords.
2. Verify your address using the email link. With the default `MAIL_MAILER=log`, that link is written to the private local application log rather than delivered externally.
3. Sign in and open the organization picker. Create an organization to become its initial Owner, or use an invitation from an existing Owner/Admin after verifying the invited address.
4. Select an organization to enter its dashboard. Existing Owner/Admin members can also add an already verified account through membership management.

```bash
# Local verification/reset mail links and application log messages:
tail -f storage/logs/laravel.log

# Container output; log-mail content is not necessarily written here:
docker compose logs -f app worker
```

Keep logged verification, password-reset and invitation links private. Configure the mail environment variables with your SMTP provider for external delivery. The worker processes queued invitations and security notifications; default log mail does not send an email to an external inbox.

Two distinct authorized accounts are needed to review an approval. Support operators must enroll and confirm TOTP on their own account before starting an approved support grant. These workflows are described in [identity.md](identity.md) and [governance.md](governance.md).

## Day-to-day development

Rebuild browser assets after frontend changes:

```bash
docker compose run --rm --no-deps node npm run build
```

For a local Vite development server, use a host Node version satisfying `^22.13.0 || >=24.0.0` and run `npm run dev`. The Compose Node service does not publish a Vite development port. ESLint checks maintained JavaScript/config files with `npm run lint`.

The worker is long-lived and must reload changed PHP code:

```bash
docker compose restart worker
```

After updating an existing checkout, preserve `.env` and its key, install locked dependencies, apply additive migrations, rebuild assets and restart the worker:

```bash
docker compose build app
docker compose up -d postgres redis
docker compose run --rm --no-deps app composer install
docker compose run --rm app php artisan migrate
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run lint
docker compose run --rm --no-deps node npm run build
docker compose up -d app worker
docker compose restart worker
```

Review migration changes and preserve needed data before applying updates. Do not use schema-reset commands against the application database. Production release procedures are separate and documented in [operations.md](operations.md).

## Tests use a separate database

[phpunit.xml](../phpunit.xml) forces PostgreSQL and the `simple_admin_testing` database. Tests may reset that database's schema. Never connect tests to real data or production infrastructure.

The PostgreSQL initialization script creates the test database when a new local volume is initialized. If your volume predates that script and the database is missing, create it once:

```bash
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" simple_admin_testing'
```

Concurrency tests start independent PHP processes and check PostgreSQL lock waits. Run the suite serially against its dedicated database; SQLite and mocked locks do not exercise these guarantees.

## Quality checks

With local PostgreSQL and Redis running, use:

```bash
docker compose run --rm app php artisan migrate
docker compose run --rm app composer validate --strict
docker compose run --rm app composer check
docker compose run --rm app composer audit --locked --no-interaction
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run lint
docker compose run --rm --no-deps node npm run build
docker compose run --rm --no-deps node npm audit --audit-level=low
docker compose run --rm app php artisan view:cache
docker compose run --rm app php artisan app:check-redis
```

`composer check` runs Pint, Larastan level 7 and Pest, including retained starter-kit PHPUnit-style tests. Run one check with `composer lint:check`, `composer analyse` or `composer test` through the same app container. Use `composer lint` to format deliberate PHP changes. The Redis command writes, reads and removes a unique short-lived cache key.

The [CI workflow](../.github/workflows/ci.yml) runs the configured gates on PHP 8.3; local Docker uses PHP 8.4. Recorded results and remaining verification limits are in the [four-phase review](phases-review.md) and phase-specific verification documents. Local success does not establish a successful remote workflow or production readiness.

## Health and shutdown

`/up` reports application liveness. `/ready` checks the database, cache and a recent worker heartbeat, returning only `ok` or HTTP 503 `unavailable`. It is not proof that every queued notification was delivered. Authorized dashboards show individual checks and sanitized tenant queue incidents. See [operations.md](operations.md) for private logs and failed-job handling.

```bash
# Stop this project's services and release their RAM; persisted data remains.
docker compose down

# Start again without reinstalling dependencies or regenerating the key.
docker compose up -d app worker
```

Do not use `docker compose down -v` unless you deliberately intend to delete this project's local database/cache volumes. Ordinary shutdown keeps those volumes for the next start.
