# Governance architecture — Phase 4

This remains one Laravel application, PostgreSQL database and Redis queue/cache. There is no external approval engine, impersonation package or new infrastructure service.

## Permissions

| Permission | Owner | Admin | Manager | Support | Viewer |
| --- | --- | --- | --- | --- | --- |
| audit.view | Yes | Yes | No | No | No |
| audit.export | Yes | Yes | No | No | No |
| approvals.request | Yes | Yes | No | No | No |
| approvals.review | Yes | Yes | No | No | No |
| support.request | Yes | Yes | No | Yes | No |
| operations.view | Yes | Yes | No | No | No |

Tenant context and policies always resolve current active memberships and grants. An organization role is not a global operator role. There is no global super-admin bypass. Governance uses session-authenticated Livewire pages; no governance mutation endpoints have been added to the bearer API.

## Structured audit history

`Audit` writes actor ID, organization ID, target type/ID, timestamp, server-generated correlation UUID, optional support subject ID, and redacted changes. The middleware ignores inbound correlation IDs and returns its generated ID in `X-Correlation-ID`.

`TransactionalAudit` wraps audited Eloquent saves/deletes and registers synchronous observers. Audit insertion and the model mutation share a transaction; an audit failure rolls back the write. Organization/membership/role assignment, invitations, structure, token issuance/individual revocation, user changes, approvals and support grants are covered. Bulk credential revocation has explicit aggregate events. Login failures omit submitted credentials and email. Global identity/login events have no arbitrary organization and are not visible to tenant administrators; inspect these only through trusted operator access.

Only structural IDs, known status/operation values, counts and timestamps may appear in changes. Personal text, reasons, email, passwords, hashes, MFA secrets, recovery codes, token values and raw session IDs are redacted or omitted. No request body or URL is serialized. Audit event names are stable technical identifiers, not translated user text.

PostgreSQL rejects UPDATE/DELETE on audit rows. Historical actor/organization identifiers deliberately have no cascading foreign keys, preserving evidence after deletion. This does **not** protect against database superusers, trigger disabling, table truncation/drop, backups being altered or privileged direct SQL. The application has no audit deletion/pruning endpoint. Retention and legally required erasure need an explicit operator policy before real deployment.

Audit history is searchable by event/target, actor ID and correlation UUID, scoped to the active tenant. CSV export requires its own policy permission, is throttled, limited to the latest 1,000 matching rows and audited. All fields are CSV-quoted and potential spreadsheet formulas are prefixed. Exports are snapshots authorized at generation, with no caching; previously downloaded files cannot be recalled after permission changes.

Model observers do not capture arbitrary SQL/pivot updates, framework session housekeeping or privileged database edits. Runtime role changes use the audited RoleAssignment model; fixed permission catalogs remain read-only. When adding a new bulk mutation, write an explicit aggregate event or use audited model operations. There is no claim of database-wide change-data capture.

## Approval workflow

The deliberately bounded high-risk operations are:

1. `revoke_org_tokens`: revoke all current API tokens bound to the active organization. Existing personal token revocation remains a self-service operation.
2. `support_access`: create one short-lived read-only support grant for a specific existing membership.

Ordinary membership management retains Phase 2/3 hierarchy and owner protections; it does not silently become an approval workflow. Expanding governance to role promotion or other destructive actions requires enforcement at every underlying mutation entry point, not merely another UI button.

Requests expire after 24 hours. States are `pending → approved → executed`, or `pending → rejected/cancelled/expired`. Approval and execution happen in **one transaction** under an organization row lock plus request row lock. The intermediate approved transition is audited inside that transaction; it is never an independently runnable queued command. Execution failure rolls back state, audit and database effects. Repeated or concurrent execution is rejected. Notifications run after commit.

The reviewer must be a different authorized account from the requester and support target, confirm their password, and still have current review permission. Self-review is additionally blocked by a database CHECK constraint. The requester must retain current operation permission at execution. Payloads are constructed server-side from tenant-scoped records and are not accepted from the browser. Requests cannot be edited after submission; cancel and create another request. Expiry is enforced when deciding and displayed immediately even before a pending row is materialized as expired.

## Restricted support impersonation

This is a view-as mode; the real authenticated identity is retained. It is not a full login as another user.

- A currently authorized Owner/Admin/Support requests access for an active, verified Manager/Support/Viewer in the same organization. Self, Owner/Admin and foreign targets are rejected.
- An independent Owner/Admin approves. The grant expires **15 minutes after approval**, so delaying its start cannot extend access.
- The requesting operator must confirm their current password and a fresh TOTP code from confirmed MFA. Recovery codes are not accepted for support step-up. Attempts are throttled. Start locks the user and grant; concurrent starts cannot consume the grant twice.
- A started grant is bound to a regenerated server-side browser session via an HMAC. It cannot be restarted, moved to another session or nested.
- On every request, middleware checks the session binding, organization, operator, reviewer and target's current access. Suspension, revoked role grants, expiry, target promotion to Owner/Admin or membership removal ends access.
- Only GET dashboard, organization details, and teams/departments are allowed. Effective permissions are the target's read permissions intersected with an explicit allowlist. Personal settings, exports, credentials, switching, all mutations and Livewire POST updates are blocked. Search/filter interactions requiring Livewire updates are intentionally unavailable in support mode.
- A persistent banner identifies the subject user ID and provides a CSRF-protected end button. Start, views, blocked requests and explicit/invalidated stops are audited. The target receives a queued security notification on start. Membership removal cascades the operational grant, while audit evidence remains.

The session keeps the operator's authentication and cannot mint credentials for the subject. Other independently authenticated sessions/API clients retain only their own real permissions. This does not conceal the operator or provide platform-wide support access. Approval separation is per account; collusion or one human controlling multiple accounts remains outside that guarantee.

## Dashboard, health and queues

The dashboard shows organization-scoped activity, pending invitations, pending approvals and tenant token/support security events, according to the actor's current permissions. Global account events are intentionally absent. An operator-grade incident response console is not implemented.

`/up` is process liveness. `/ready` returns only `ok` or HTTP 503 `unavailable`; it checks PostgreSQL, configured cache round-trip, and a queue worker heartbeat newer than 90 seconds. The public response exposes no connection/exception details. The authorized dashboard provides individual check results.

The worker writes a short-lived heartbeat on queue loops. This detects missing/stale workers, not delivery success, backlog latency, per-queue coverage or all blocked-job conditions. One healthy worker can satisfy readiness. A long job can make readiness stale; set appropriate worker/job limits and monitor queues separately for a real deployment.

Queue payloads receive server-derived organization/correlation metadata. Terminal failures create sanitized tenant queue incidents and `queue.failed` audit/security log events. UI displays only its own incidents, never exception messages or serialized payloads. Failures without tenant context remain operator-only. Native `queue:failed` retains detailed diagnostics and must remain private. Redis/database outages can prevent failure recording; this is not an outbox or guaranteed external alerting system.

Committed audit events emit minimal JSON records to the rotating `security` log channel after commit. These contain IDs and event names, not changes or credentials. Logging is local; configure restricted access, retention and external collection for deployment. Default log mail remains private and must be replaced with tested delivery configuration.

See [threat model](threat-model.md), [operations](operations.md), [API examples](api-examples.md), and the [verification record](phase-4-verification.md).
