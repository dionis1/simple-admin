# Legacy data migration limitations

## What is preserved

The repository's Git history remains available. The original migration names `2014_10_12_000000_create_users_table` and `2014_10_12_100000_create_password_resets_table` are retained, including the 32-bit user primary key. The additive Phase 1 migration adds nullable email verification timestamps and new password-reset/session tables. It does not delete users, modify password hashes, or drop the old `password_resets` table. Fresh installs also retain that old reset table for migration continuity.

Existing users start unverified; no addresses are automatically trusted. Standard Laravel bcrypt hashes remain usable, subject to validating the real legacy hash format/configuration. Existing reset links are invalid because Fortify uses `password_reset_tokens`; users must request fresh links. Old sessions are not imported, so users must sign in again.

## What is unknown

The repository does not include the installed jamesmills/laravel-admin package's production schema, published migrations, or data. Actual role, permission, pivot, extra user columns, and foreign-key relationships must be inventoried separately. Removing its code does not authorize deleting its tables or data. No replacement roles, privileges, organizations, or automatic privilege mappings are created. Legacy `App\\User` references in polymorphic tables require explicit mapping to `App\\Models\\User` if those records are later reused.

The new application is configured for PostgreSQL. Laravel migrations do not copy a legacy MySQL database into PostgreSQL. Differences in identity sequences, unsigned integers, collations, case-sensitive email uniqueness, time zones, date formats, and package-owned schema must be resolved with a reviewed import plan. Do not change the connection and assume the data will move.

## Required before a production migration

1. Back up and restore the real database into an isolated staging environment; record row counts, schema, migration ledger, hash algorithms, and external integrations. Validate a tested restore procedure.
2. Inventory package migrations/tables and user foreign keys. Decide explicitly which concepts and historical records are retained for later phases. Do not run package rollback/uninstall migrations.
3. Build and rehearse an import into a separate PostgreSQL database, preserving IDs, password hashes, and timestamps. Reset identity sequences after importing IDs. Audit case-folded duplicate emails before normalizing them.
4. Reconcile the migration ledger with the two retained baselines and any previously published migrations. Only then apply the new migrations in staging. Unexpected table/column collisions deliberately fail; this code does not silently skip schema mismatches.
5. Verify user counts, representative authentication, verification email delivery, password reset, retained relationships, and planned future authorization. Phase 2 dashboard access requires a validated active membership; legacy roles and privileges are not mapped automatically.
6. Plan cutover, write freeze, key/secret handling, and rollback from the tested backup. Avoid resuming writes to two divergent databases. Keep the original database intact until reconciliation is approved.

Never use `migrate:fresh`, `db:wipe`, `migrate:reset`, or volume deletion against an existing/production database. Rolling back the new auth migration removes verification timestamps and new reset/session records; it is not a data-preserving production rollback. Existing reset records remain confidential and need a separately reviewed retention policy.

The included upgrade regression test verifies only the repository's known user/reset schema and additive auth migration. It cannot establish compatibility with unknown production tables, data, or package migrations. Production migration remains blocked on that inventory and staging rehearsal; no production data was supplied during Phase 1.


## Phase 2 schema and authorization

Phase 2 creates `organizations`, `memberships`, `roles`, `permissions`, `permission_role`, and `role_assignments`. These names may collide with tables or migration names from an installed legacy admin package. **Do not apply the migration blindly to such a database.** Inventory and reconcile those schemas in staging first. The migration intentionally fails on collisions and does not drop, rename, import, or assign privileges from legacy tables.

Existing users receive no automatic organization membership or inherited global privileges. After verification they can create their own new organization or be added by an authorized Owner/Admin. Initial ownership belongs only to the creator of that new organization. The old user IDs and hashes remain unchanged. Organization removal/import/production cutover are outside Phase 2. Account deletion is blocked while a user has memberships; the PostgreSQL foreign key also restricts direct user deletion.

## Phase 3 identity migration

New invitations, departments, teams, team memberships and personal access token tables must be checked for name/schema collisions before migrating a legacy database. Existing users receive active/en defaults; this does not import legacy administrative privileges. Back up and rehearse the migration in staging. Preserve APP_KEY for encrypted MFA secrets and queued notifications. Rollback drops Phase 3 security/structure data and is not a safe production recovery strategy. No production import or production rollback has been verified.
