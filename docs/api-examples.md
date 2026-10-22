# API examples

Create an expiring token on Account security for the currently selected organization. A token's abilities cannot exceed current membership permission. Copy its plaintext value once into a private environment variable; do not commit it or include it in URLs.

```bash
# Examples use a placeholder. Supply a real token privately.
export SIMPLE_ADMIN_TOKEN='YOUR_PRIVATE_TOKEN'
curl --fail-with-body http://localhost:8000/api/v1/memberships \
  -H "Authorization: Bearer $SIMPLE_ADMIN_TOKEN" \
  -H 'Accept: application/json'

curl --fail-with-body http://localhost:8000/api/v1/memberships/123 \
  -H "Authorization: Bearer $SIMPLE_ADMIN_TOKEN" \
  -H 'Accept: application/json'

# Replace the membership ID and role_id with authorized IDs from this organization.
# PATCH currently requires both role_id and status, even if the role is unchanged.
curl --fail-with-body -X PATCH http://localhost:8000/api/v1/memberships/123 \
  -H "Authorization: Bearer $SIMPLE_ADMIN_TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data '{"role_id":456,"status":"suspended"}'
unset SIMPLE_ADMIN_TOKEN
```

Use HTTPS outside local development. Tokens are never supplied as query parameters. No organization ID/header chooses a tenant: the authenticated token supplies server-validated context. Cookies alone cannot authenticate API requests. GET requires `memberships.view`; PATCH requires `memberships.update`, and a role change also requires `roles.assign`. Policies still enforce hierarchy and owner constraints.

The list is paginated (20 records per page, `?page=2`). Current response fields and validation are defined by `MembershipController`; no full user object, password, MFA secret or token hash is returned. JSON error statuses include 401 for missing/invalid/expired credentials, 403 for missing/revoked authority, 404 for missing/foreign targets, 422 for validation and 429 for rate limits. Responses include a server-generated `X-Correlation-ID` to correlate incidents without sharing credentials.

Governance pages are browser/session-only. There are no support-start, approval-execution or audit-export bearer endpoints. Audit CSV uses a permission-checked, throttled browser download. Never automate a browser session to bypass these boundaries.
