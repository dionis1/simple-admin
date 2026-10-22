# Organization access and authorization — Phase 2

## Context and trust boundaries

Users may belong to multiple organizations. After authentication/verification they land on `/organizations`, which lists only their memberships. Creating a new organization atomically provisions five tenant-owned roles, their permission grants, and the creator's Owner membership. Existing users are not silently assigned legacy roles or organizations.

The switch action accepts an organization ID only as a lookup candidate. It queries the authenticated user's active memberships, the active organization, and an existing role assignment before storing the validated ID in the server-side session. Suspended organizations/memberships and unknown/foreign IDs are rejected with 403. An invalid switch leaves the previous selection unchanged.

`TenantContext` always re-queries the current user/membership/organization/role. It never uses a browser-supplied permission list, component organization ID, stale Eloquent relationship, or cached effective permissions as authority. The selected ID alone grants no access. Missing, malformed, deleted, foreign, suspended, or unassigned membership context fails closed. A verified login alone is insufficient.

Tenant routes use `EnsureTenantContext` after authentication and email verification. Dashboard access also checks its Gate; organization/member/role/permission pages use Policies. Tenant middleware is registered as persistent Livewire middleware. Every public mutation independently resolves context and authorizes again; previously mounted components cannot retain privileges after suspension, deletion, or role changes. IDs from old tabs are looked up again inside the active organization and rejected if they belong elsewhere.

The organization picker and personal account settings deliberately do not require an active tenant. Users must be able to select a workspace or recover/update their account even after access is revoked. Global permission catalog keys are not user grants: access is resolved through the active membership's tenant-owned role.

## Permission matrix

| Resource / action | Owner | Admin | Manager | Support | Viewer |
| --- | --- | --- | --- | --- | --- |
| dashboard.view | Yes | Yes | Yes | Yes | Yes |
| organizations.view | Yes | Yes | Yes | Yes | Yes |
| organizations.update (name) | Yes | Yes | No | No | No |
| memberships.view | Yes | Yes | Yes | Yes | No |
| memberships.create | Yes | Yes | No | No | No |
| memberships.update | Yes | Yes | No | No | No |
| memberships.delete | Yes | Yes | No | No | No |
| roles.view | Yes | Yes | Yes | No | No |
| roles.assign | Yes | Yes | No | No | No |
| permissions.view | Yes | Yes | Yes | No | No |

Permission keys are seeded by the additive migration. Each organization receives its own five roles and role-permission links. The role and permission interfaces are read-only catalogs, not permission editors. There is no implicit global Owner/Admin override and no custom role builder.

Permission checks are necessary but are supplemented by assignment and target hierarchy rules:

- Owners may manage other members and assign any built-in role in their organization.
- Admins may assign/manage Manager, Support, and Viewer. They cannot assign Owner/Admin, modify peer Admins, or modify Owners.
- Users cannot change their own role, including self-promotion or self-demotion. New-organization Owner provisioning is the explicit bootstrap exception; it creates no privileges in any other organization.
- Adding a member requires a verified, already registered account, selected by exact normalized email. Duplicate memberships and unknown/unverified accounts are rejected. There is no invitation, password reset administration, or account impersonation flow.
- Membership suspension takes effect on subsequent authorization. Reactivation restores that membership's existing or explicitly selected authorized role. Organization suspension is supported as an access state but no suspension-management workflow is included.

## Ownership and transactions

Every sensitive organization/membership mutation starts a database transaction, locks the organization row using `FOR UPDATE`, then authorizes against fresh data. All mutations for that organization therefore serialize. Final-owner checks execute inside the same lock as role/status/removal changes, avoiding a concurrent check-then-write gap.

An active Owner membership cannot be removed, suspended, or demoted if it would leave no other active Owner membership. Suspended owners do not count. To transfer ownership, an Owner first adds/promotes another verified account to Owner; that other Owner can then demote the original. An Owner can remove/suspend their own membership only if another active Owner remains. Roles cannot be changed by their own holder.

Account self-deletion is blocked while any membership exists, including suspended memberships. Membership additions and account deletion lock the user row to coordinate concurrent changes; a restrictive PostgreSQL user foreign key supplies an additional database boundary. This guard does not add an account-management module.

The guarantee applies to the application's supported mutation paths. Direct privileged database changes can bypass application owner-count invariants; operators must not directly modify memberships/grants without equivalent checks. No SQL trigger or PostgreSQL row-level security layer is claimed.

## Schema boundaries

- Unique `(organization_id, user_id)` membership; indexed user/status and organization/status lookups.
- Unique `(organization_id, name)` role; database checks restrict status/role values.
- One role assignment per membership. Composite foreign keys bind both membership and role to the assignment's organization, so forged cross-tenant assignments fail at the database boundary too.
- Unique permission key and resource/action pair; unique role/permission pair.
- User foreign keys preserve the original 32-bit user ID. New organization/access records use Laravel's bigint IDs.
- No global Eloquent tenant scope: each operation explicitly scopes through the validated organization, with Policies guarding supplied models. New tenant resources must follow that pattern and receive negative isolation tests before exposure.

## Verification and scope

The Pest suite checks all 50 role/permission decisions and all 25 role/page combinations, plus forged IDs, mixed-organization ownership, switching, suspended/unassigned memberships, stale Livewire actions, input validation, assignment hierarchy, SQL constraints, account deletion, and final-owner cases. Concurrent-owner tests start independent PHP processes, hold the organization row in the parent connection, confirm both workers are blocked on PostgreSQL locks, then release them and assert exactly one mutation succeeds with one active Owner remaining.

Run the suite serially on the dedicated `simple_admin_testing` PostgreSQL database. Concurrency workers refuse to run against any other database name/environment. Runtime lock markers are temporary files and are cleaned up. See the README verification commands.

Phase 2 adds no audit workflows, custom-role editing, invitations, organization lifecycle workflows, advanced account management, business CRUD, or production legacy-data migration. See `legacy-data.md` before applying these table names to a legacy installation.
