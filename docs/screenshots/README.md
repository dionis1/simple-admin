# Screenshot inventory and verification

Reviewed on 2026-10-22. The 12 JPEG files below were already present in the working tree when this follow-up review began. They show the application's Northstar Studio demo workspace, with fictional names and `example.test` email addresses. They are retained application images, not newly captured evidence from this review.

## Included images

| Image | Visible purpose | Dimensions |
| --- | --- | --- |
| [Dashboard](dashboard.jpg) | Workspace counts, recent activity, and service health | 1425 × 1128 |
| [Organization management](organizations.jpg) | Active organization name and status | 1440 × 1000 |
| [Memberships](users.jpg) | Synthetic members, roles, status, filters, and bulk controls | 1425 × 1485 |
| [Roles](roles-permissions.jpg) | Fixed organization roles and permission lists | 1425 × 1206 |
| [Teams and departments](teams-departments.jpg) | Department/team forms, ownership, and member assignments | 1425 × 1256 |
| [Invitations](invitations.jpg) | Invitation form and pending synthetic recipients | 1440 × 1000 |
| [Approvals](approvals.jpg) | Request form and existing workflow entries | 1425 × 1723 |
| [Audit history](audit-history.jpg) | Filtered event history and redacted changes | 1440 × 1000 |
| [Account security](account-security.jpg) | Account status and authenticator setup entry point | 1440 × 630 |
| [Organization switcher](organization-switcher.jpg) | Two demo organizations and organization creation | 1440 × 1000 |
| [Permission catalog](permission-catalog.jpg) | Resource/action permission definitions | 1425 × 2110 |
| [Password settings](password-settings.jpg) | Empty password-change form | 1440 × 1000 |

The main [README gallery](../../README.md#application-screenshots) embeds the nine requested areas. The remaining three images provide additional context here. All files decoded successfully and were visually inspected together; membership details were also inspected at full resolution. No visible passwords, recovery codes, invitation URLs, session tokens, or personal contact addresses were observed.

## Evidence limits

- The images have a similar desktop presentation, but their dimensions vary. Several show long pages; the security image is shorter. The original viewport settings and capture script were not available, so identical capture settings are not claimed.
- The security image shows the option to set up an authenticator. It does not prove completed enrollment, successful TOTP challenges, or recovery-code use. Captures of those secret-bearing screens are deliberately unnecessary for the gallery.
- Visible controls and workflow entries establish interface presentation, not successful execution of every operation. Security and concurrency guarantees are backed by the linked automated tests and design documents.
- This review opened `http://localhost:8000/` in the browser and observed the working welcome page. It did not sign into the existing demo account or recreate authenticated screenshots because its credentials were not available in the supplied task. Existing credentials were not reset.
- Remote GitHub Actions status, a clean installation into fresh volumes, production deployment, and external email delivery were not verified in this follow-up.

## Local checks in this review

Docker Compose reported running app/worker services and healthy PostgreSQL/Redis services. `php artisan migrate:status` reported all eight migrations applied, and `php artisan app:check-redis` passed its cache round trip. `docker compose exec -T app composer check` passed Pint (161 files), Larastan level 7, and all 309 tests with 836 assertions in 136.06 seconds. Tests used the dedicated test database, separate from application data. The documentation link check found 101 existing local targets with none missing, and `git diff --check` passed.

See [testing and quality](../../README.md#testing-and-quality), the [four-phase review](../phases-review.md), and [local development](../local-development.md) for commands and earlier evidence.

### Correction after reviewing CI screenshots

The earlier local Pint result used its existing cache. A follow-up run with `--test --cache-file=/tmp/simple-admin-ci-review-before.cache` reproduced both reported `method_argument_space` failures. Pint corrected four calls in `ApiTokensTest.php` and `StructureLifecycleTest.php`; a new check using a separate fresh cache passed all 161 files. The earlier cached pass alone was insufficient formatting evidence. No assertions or application behavior changed, and remote CI still requires verification after pushing the fix.

## Refreshing the gallery

1. Start the existing local Compose application using the [setup guide](../local-development.md). Preserve existing environment keys and database volumes.
2. Use verified demo accounts and entirely fictional organizations, names, and `example.test` addresses. Do not reuse personal accounts or commit credentials.
3. Capture the actual authenticated interface with one desktop viewport, such as 1440 × 1000. Use full-page captures consistently where long lists need to remain visible, and record the capture settings.
4. Keep password fields empty and omit tokens, verification links, MFA QR codes, recovery codes, cookies, and browser storage from all captures.
5. Inspect every saved image at full size, update this inventory, and verify the README's relative image links before publishing.
