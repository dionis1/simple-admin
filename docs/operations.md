# Operations and migration safety

This is deployment guidance, not a verified production deployment. The supplied Compose stack is a local PHP development server. It has no TLS termination, production PHP-FPM/web-server configuration, secret manager, backup scheduler or production process supervisor. No new infrastructure is required by the application, but those deployment responsibilities must be supplied by an operator.

## Release procedure

1. Rehearse the exact release and migration on a restored staging copy. Review schema names, constraints, permission grants and the migration plan. Preserve original migration identities and existing 32-bit user IDs. The application does not import Laravel 5.6/MySQL business data automatically.
2. Use PHP 8.3+ with required extensions, PostgreSQL 17 and Redis. Build assets with a Node version satisfying package.json (currently 22.13+ on 22.x, or 24+). Use a dedicated deployment identity and least-privileged runtime database user. Keep database/cache services private.
3. Configure APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, secure session cookies, a private database/session/cache/Redis connection and actual SMTP delivery. Preserve APP_KEY securely: MFA secrets and encrypted queued notifications depend on it. Never run key:generate on an existing installation as a routine release step.
4. Run the full quality commands below before deploying. Install production PHP dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`; build assets with the locked Node dependencies in a build environment. Serve only the public directory with a suitable production web server/PHP runtime. Never publish the repository root or use artisan serve for live traffic.
5. Take a consistent database backup and private upload/config/key backup. Enter a controlled maintenance window if schema/traffic compatibility requires it. Run `php artisan migrate --force --isolated` against the intended database. Inspect results; do not use migrate:fresh, migrate:refresh or volume deletion on existing data.
6. Run `php artisan optimize`, verify cached views/routes/config and restart long-lived workers so they load the new code. Operate `queue:work --tries=3 --timeout=60` under a supervisor appropriate to the environment. Configure cache/queue timeouts and Redis retry_after greater than worker timeout. Do not run a second old-code worker during incompatible migrations.
7. Check `/up`, `/ready`, a real login/MFA, tenant switching, a read-only page and a test notification with the intended SMTP provider. Monitor errors and failed jobs. Leave maintenance mode only after release checks succeed. A successful local check does not establish production correctness.

## Quality gates

```bash
docker compose exec app composer check
docker compose exec app composer audit --locked --no-interaction
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run lint
docker compose run --rm --no-deps node npm run build
docker compose run --rm --no-deps node npm audit --audit-level=low
docker compose exec app php artisan view:cache
docker compose exec app php artisan app:check-redis
```

Pint, Larastan level 7 and all tests run through composer check. ESLint checks maintained JavaScript/config files; Blade rendering is covered by HTTP/Livewire tests and view compilation. Dependency scans fail CI on reported vulnerabilities, including development dependencies; a clean scan is time-specific, not proof of absence of vulnerabilities. GitHub Actions is configured to run these gates, but remote execution must be checked after push. Concurrency tests run serially on simple_admin_testing, never the application database.

## Health, logs and incidents

Run the existing app and worker with `docker compose up -d app worker`; restart the worker after code changes. `/ready` checks DB/cache and a worker heartbeat within 90 seconds. It returns no internal details. The dashboard exposes service health and only the current tenant's sanitized queue failures to operations-authorized members.

`storage/logs/security-*.json` contains rotating JSON event metadata. Audit changes remain in PostgreSQL. Protect all logs and native failed_jobs data: mail logs and exception messages can contain sensitive information. UI failure records deliberately omit raw exceptions and payloads. Do not expose database diagnostics in an HTTP endpoint.

Use `php artisan queue:failed` as a trusted operator. Investigate before retrying a specific failed job with queue:retry; notifications may already have been delivered. Never assume arbitrary jobs or email delivery are exactly-once. Redis/DB failure can prevent incident persistence. There is no external alerting or durable transactional outbox in this phase.

## Backup and restore

Define retention, encryption, off-host storage, restore frequency, and recovery time/data-loss objectives before live operation. Back up PostgreSQL with a consistent tool such as pg_dump custom format. Back up private media and APP_KEY/config separately through secure channels. A database dump alone cannot recover encrypted MFA/notifications without the matching key. Redis persistence is not a substitute for queue recovery planning.

Local example (the file is private and gitignored):

```bash
umask 077
docker compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' \
  > storage/app/private/database-backup.dump

# Restore into a NEW, isolated database; do not overwrite the application database.
docker compose exec postgres sh -c 'createdb -U "$POSTGRES_USER" simple_admin_restore'
docker compose exec -T postgres sh -c 'pg_restore -U "$POSTGRES_USER" --no-owner --exit-on-error -d simple_admin_restore' \
  < storage/app/private/database-backup.dump
```

Choose a unique restore database name if the example name exists. Do not use --clean against an unknown database. For a rehearsal, run an isolated application/config pointing at the restored database and a separate cache/session/queue namespace. Block outbound SMTP and job processing initially so restored queued notifications are not accidentally resent. Verify migration status, row counts, relationships, audit constraints, tenant boundaries, private media and MFA decryption using authorized test accounts. Reconcile restored sessions/tokens, queued jobs and invitation expiry before permitting live traffic. Do not test schema-resetting PHPUnit commands against a restored production copy.

Restoring a backup can resurrect previously revoked sessions, tokens or invitations and omit recent audit evidence. Plan credential invalidation and incident reconciliation as part of recovery. Keep the original system isolated for forensic review; do not let old and restored systems process the same queue.

## Phase 4 schema and rollback

The additive migration creates audit_events, approval_requests, support_grants and queue_incidents, six permission catalog entries, indexes, checks and composite grant foreign keys. It installs the application-owned `simple_admin_protect_audit_events()` trigger function. Check for conflicting existing objects before applying the migration. Its CREATE OR REPLACE definition permits dedicated test database rebuilds; it is not a license to overwrite unknown legacy functions in production.

Actor/reviewer identifiers are historical references rather than cascading user foreign keys. Operational grants are removed when their target membership is removed; historical audit evidence remains. Approval/operational records restrict unsupported organization deletion. Audit events deliberately preserve historical organization IDs independently of live records.

Do not roll back governance tables after live usage without a retention/recovery plan: down drops audit evidence, approvals and grants and removes the new permission grants. Prefer a reviewed forward fix. No production rollback, legacy import or production restore is verified by this phase.
