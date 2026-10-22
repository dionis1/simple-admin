# Identity management — Phase 3

## Membership and structure

Organization administrators manage scoped display names, job titles, departments, roles and active/suspended/deactivated memberships. They cannot disable another person's global identity across unrelated organizations. Owner/Admin hierarchy and self-promotion restrictions remain enforced by policies and transactional actions. Bulk lifecycle changes authorize every target and roll back the whole operation on failure.

Departments and teams belong to one organization. Composite foreign keys prevent cross-organization assignments. A team owner must be an active member; transfer team ownership before disabling/removing that member or deactivating their account. Team ownership grants no implicit RBAC privileges. The final active organization Owner cannot be removed or disabled. Owners counted for this invariant must also have active global accounts.

## Invitations

Owner/Admin users can issue or revoke invitations. Links expire after 48 hours, use random tokens stored as SHA-256 hashes, and are single-use. Reissuing revokes the previous pending invitation. Notifications are queued and encrypted; keep log mail private.

The recipient must register, verify the exact invited email address and sign in before accepting. Acceptance locks and rechecks the invitation, recipient, organization and inviter's current authority. Disabled existing memberships cannot be revived through invitations. Expired, revoked, consumed and unauthorized invitations fail closed.

## Account security

Use Settings → account security for TOTP enrollment, confirmation, recovery codes, session listing/revocation and API tokens. Native Fortify challenge and recovery functionality is reused. User-row locks serialize MFA changes and recovery-code consumption. Password confirmation protects sensitive settings; operations also validate the current password with throttling.

Sessions show metadata and opaque HMAC references rather than raw session IDs. Users can revoke only their own sessions. Password/email/MFA changes and account lifecycle changes revoke other sessions and tokens, rotate remembered credentials, and queue security notifications. Security responses prevent caching and referrer leakage.

Global deactivation/reactivation is self-service with password confirmation. Deactivated users can authenticate, including MFA, to recover their own account but cannot access tenant resources or APIs. Reactivation does not restore suspended/deactivated memberships. Global suspension is a trusted operator state and denies authentication/access; no global platform-admin module is provided.

## API tokens

Create tokens on the security page for the active organization. Choose explicit abilities currently allowed by your membership and an expiry of at most 90 days. Wildcard abilities are forbidden. The plaintext token is displayed once; store it privately. Revoke tokens on the same page.

Bearer-only endpoints:

| Method | Endpoint | Permission |
| --- | --- | --- |
| GET | /api/v1/memberships | memberships.view |
| GET | /api/v1/memberships/{id} | memberships.view |
| PATCH | /api/v1/memberships/{id} | memberships.update; roles.assign for role changes |

PATCH accepts validated role_id/status fields and enforces target hierarchy and owner invariants. List responses paginate 20 records. Requests are limited to 60 per minute per user. Send `Authorization: Bearer YOUR_TOKEN` and `Accept: application/json`. Cookie-only authentication is rejected. Tenant context comes from the stored token organization, not request headers or supplied organization IDs. Every request checks token abilities and current verified/active identity, membership and RBAC. A token cannot preserve privileges after permission revocation.

Tokens have mandatory expiry, are hashed in storage and can be revoked individually. Membership role/status changes revoke that organization's tokens. No password, MFA or global-user administration API is exposed.

## Local operation and localization

Run `docker compose up -d app worker` after initial README setup. Notifications use Redis queues; default mail is log-only. Restart the worker after PHP changes. Inspect failures with `docker compose exec app php artisan queue:failed`; configure SMTP separately for delivery. Expired tokens are denied immediately; optionally remove expired rows with `php artisan sanctum:prune-expired`.

The language switcher supports English and German, using a validated session/account preference. Validation, pagination, authentication and implemented management interfaces have translations. API messages use the authenticated user's locale.

## Data boundaries

The migration is additive, but applying it to production requires staging validation and backups. No legacy production data was imported. Rollback removes Phase 3 identity data, including MFA configuration and tokens, and must not be treated as a safe production undo. Preserve APP_KEY for encrypted MFA secrets and queued notifications. See legacy-data.md.
