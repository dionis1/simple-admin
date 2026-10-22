# Architecture and implementation decisions

Simple Admin is a modular monolith: one Laravel application, one PostgreSQL database, and Redis for cache and queued work. Related behavior is grouped into actions, policies, models and Livewire components. Laravel validation, Eloquent, transactions, notifications and queues provide the shared infrastructure; ordinary queries are not wrapped in generic repository layers.

## Technology choices

| Area | Repository configuration |
| --- | --- |
| Application | Laravel 13; PHP 8.3 minimum, PHP 8.4 in the local Docker image |
| Interface | Livewire 4, Flux UI 2, Tailwind CSS 4 and Vite 8 |
| Authentication | Laravel Fortify sessions, verification, password recovery and TOTP |
| API credentials | Laravel Sanctum tokens bound to an organization and explicit abilities |
| Data and background work | PostgreSQL 17; database sessions; Redis 7.4 cache and queue |
| Verification | Pest 4, Pint, Larastan level 7, ESLint and GitHub Actions |

Dependency constraints are in [composer.json](../composer.json) and [package.json](../package.json); committed lockfiles record resolved dependencies. Composer resolves against PHP 8.3, which is also the CI target. The locally recorded executable checks used Docker PHP 8.4. Node must satisfy `^22.13.0 || >=24.0.0`.

## Code map

| Location | Responsibility |
| --- | --- |
| [app/Actions](../app/Actions) | Application operations for authentication, organizations, memberships, invitations, structure, security and governance |
| [app/Tenancy](../app/Tenancy) | Fresh active-membership resolution and final-active-owner protection |
| [app/Policies](../app/Policies) and [app/Enums](../app/Enums) | Resource authorization, fixed permissions, role names and access states |
| [app/Models](../app/Models) | Eloquent relationships, casts and persistence behavior |
| [app/Http/Middleware](../app/Http/Middleware) | Account/tenant eligibility, bearer API context, support-session restrictions, locale and correlation IDs |
| [app/Livewire](../app/Livewire) | Organization, membership, invitation, structure, security and governance screens |
| [resources/views](../resources/views) | Authentication/account pages, layouts and shared Blade/Flux presentation |
| [app/Governance](../app/Governance) | Transactional audit recording, redaction, history queries, health and sanitized queue incidents |
| [app/Notifications](../app/Notifications) | Queued invitations and security notices |
| [routes](../routes) | Web, account settings, versioned membership API and console routes |
| [database/migrations](../database/migrations) | Retained legacy baselines and additive foundation, tenancy, identity and governance changes |
| [tests/Feature](../tests/Feature) and [tests/Support](../tests/Support) | HTTP/Livewire behavior, authorization boundaries, upgrade regressions and independent-process concurrency checks |

## Request and authorization boundaries

Web authentication is session-based. Tenant screens require an authenticated, verified, active identity and an active membership in the selected organization. Browser-supplied IDs are lookup candidates; policies resolve authority from the persisted membership and fixed role catalog. Livewire actions authorize again on the server, including subsequent component updates.

The bearer API derives its organization from the stored token. Every request requires both a permitted token ability and current membership permission. A valid token cannot retain authority after membership revocation. See [tenancy and authorization](tenancy.md), [identity management](identity.md) and [API examples](api-examples.md).

Sensitive membership and governance mutations lock the organization within a database transaction and recheck authorization. Account credential operations also use user-row locks where required. Composite foreign keys constrain tenant relationships; owner checks protect the final active owner. Audited Eloquent writes persist their safe audit event in the same transaction. Approval decisions execute under locks and prohibit self-review. The restricted support view retains the real operator identity and allows only approved read-only routes. Exact guarantees and limitations are in [governance](governance.md) and the [threat model](threat-model.md).

Keep controllers and components focused on HTTP/UI responsibilities; extract actions where an operation has meaningful business rules. Local/test lazy-loading prevention detects accidental lazy loads. Follow [AGENTS.md](../AGENTS.md) for validation, locking, localization, audit and testing conventions.

## Starter provenance and legacy compatibility

The modern foundation was imported from the [official Livewire starter kit at commit `0f62a26`](https://github.com/laravel/livewire-starter-kit/tree/0f62a26c4e4b401c1300930f47d72a497add8cce). Registration, verification and password confirmation were selected in its installer. TOTP and recovery codes were enabled during the identity phase; passkeys remain excluded. Installer-only tooling was removed. Plain Vite replaces upstream Vite Plus, and system fonts avoid remote font downloads during builds.

The replacement removed the Laravel 5.6 / jamesmills admin / Bootstrap 4 / Vue 2 / Laravel Mix foundation while preserving repository history and the original user migration identities. User IDs remain 32-bit to avoid silently changing unknown legacy foreign keys.

This is not an automatic MySQL-to-PostgreSQL importer or a verified in-place production upgrade. Existing business data needs a separate inventory, migration design and rehearsal. Read [legacy-data.md](legacy-data.md) before connecting an existing database. The supplied Compose configuration is for local development; production prerequisites and recovery procedures are documented in [operations.md](operations.md).

Implementation and recorded verification are summarized in the [four-phase review](phases-review.md). Deployment, external email and production legacy imports remain separate verification responsibilities.
