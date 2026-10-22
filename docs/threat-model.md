# Threat model

## Assets and boundaries

Assets include identity/MFA secrets, browser sessions, bearer tokens, tenant membership data, invitation tokens, approval decisions and audit evidence. The browser is untrusted. Laravel's authenticated identity, server session, freshly resolved membership/policy and database constraints provide authority. Redis/PostgreSQL, APP_KEY holders, deployment operators and database superusers are trusted infrastructure boundaries.

| Threat | Implemented control | Residual risk |
| --- | --- | --- |
| Forged organization/resource IDs | Scoped queries, fresh membership policies, composite foreign keys | No PostgreSQL row-level security; privileged SQL remains trusted |
| Stale authorization / suspended identities | Fresh policy checks, token permission intersection, per-request support validation | Authorization occurs at defined operation/read boundaries, not retroactively on downloaded data |
| Self-promotion / final owner removal | Role hierarchy, no self-role change, transactional organization lock, active-owner/team-owner guards | Direct SQL can bypass application invariants |
| Replayed high-risk approvals | Fixed server payload, expiry, organization/request locks, state checks and no self-review CHECK | Approval uses distinct accounts; collusion is not prevented |
| Support privilege escalation | Independent approval, password + TOTP, single-use 15-minute grant, session HMAC, target/read allowlists | Read access reveals permitted organization data; unrelated operator sessions remain independent |
| Leaked audit/CSV data | Tenant permissions, structural allowlist redaction, no-store, export limit, formula escaping | IDs are still personal/operational data; downloaded CSV needs safe handling |
| Audit tampering | Synchronous transactional writes and PostgreSQL update/delete trigger | Not cryptographically chained, externally immutable or safe from database administrators |
| Credential/token theft | Hashing, encrypted MFA/notifications, same-origin sessions, expiry/revocation, no subject credential issuance | APP_KEY loss/theft is critical; browser compromise and authorized account takeover remain risks |
| Log/exception leakage | Generated correlation IDs; no request bodies in audit/security logs; sanitized tenant queue incidents | Native diagnostic logs/failed_jobs can be sensitive and need restricted operator access |
| Duplicate execution / races | PostgreSQL lock-tested decision, support start, invitation/recovery and owner invariants | No exactly-once guarantee for email delivery or arbitrary future external side effects |
| Resource exhaustion | Throttles, pagination, bounded exports/search input | No distributed load or denial-of-service test; database search can scan growing audit history |

## Explicit limits

The application is not certified, penetration-tested or demonstrated production-ready. No production deployment, real SMTP provider, disaster-recovery drill or external monitoring platform has been verified. Audit/queue retention, privacy erasure, key management, organizational approval policy, backup recovery objectives and infrastructure access controls require owner decisions before live use. No default account, debug access path or global bypass has been added.

The threat model assumes TLS and secure production cookies; Docker's local HTTP development server is not a production network boundary. See operations.md for deployment prerequisites and restore procedure.
