# Phase 4 verification

Local Docker verification on 2026-10-22. These results do not establish production readiness.

| Check | Result |
| --- | --- |
| Additive Phase 4 migration | Applied to the existing local application database; prior migrations/data retained |
| composer check | Passed: Pint, Larastan level 7, 308 tests / 830 assertions |
| PostgreSQL concurrency | Passed: approval executes once; support grant starts once; prior owner, invitation and MFA recovery races retained |
| Clean npm ci, ESLint, production build | Passed |
| Composer locked dependency audit | No security vulnerability advisories reported |
| npm audit --audit-level=low | Zero vulnerabilities reported, including development dependencies |
| Blade view compilation | Passed |
| Redis round trip | Passed |
| Actual local readiness | Database, cache and worker heartbeat healthy |
| Structured security logs | JSON records parsed successfully without exposing their contents |
| Backup restoration | Local pg_dump restored successfully into a newly created temporary PostgreSQL database; audit trigger and Phase 4 migration retained; temporary database/dump removed |
| Working-data safety | No production database accessed; tests ran only on simple_admin_testing |

Security coverage includes the governance role/permission matrix, tenant-scoped audit search/export/dashboard/queue incidents, nested secret redaction, CSV formula escaping, audit immutability and rollback on insertion failure, no self-review, rejection/cancellation/expiry, requester permission revocation, atomic failure rollback, invalid/foreign targets, MFA step-up, session-bound support access, protected routes/Livewire writes, grant expiry/revocation and removal, and worker correlation cleanup. The concurrency tests observe actual PostgreSQL lock waits from independent PHP processes.

Approval scope is limited to organization-wide API token revocation and read-only support grants. Existing ordinary membership operations retain their existing authorization rules. Audits cover supported application/model mutation paths, not arbitrary SQL. Historical actor references intentionally survive identity deletion. See governance.md and threat-model.md for the precise guarantees and limits.

Not verified: browser visual review, remote GitHub Actions execution, external SMTP delivery, production deployment/load/security testing, production migration/import or full disaster recovery. The earlier browser tool approval-review usage-limit block was not retried. The local restore check verifies database restoration, not a complete production application/MFA/media/queue recovery drill.

GitHub Actions now includes PHP tests/Pint/Larastan, frontend lint/build, Blade compilation and both dependency scans. Its remote result must be inspected after a push; local success is not a claim that remote CI ran.
