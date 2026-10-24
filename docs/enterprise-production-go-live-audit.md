# Enterprise production go-live audit

## Scope and result

Phase 3F-30 audited Laravel/config and its 63-migration baseline. Commerce C1/C2 subsequently adds one additive migration, making the current inventory 64, without changing the deployment/readiness safety contract. No VPS or production provider was contacted and no production business record was mutated.

The codebase already has immutable image/digest release governance, release manifests/certificates, migration-risk scanning, guarded databases, non-root containers, Redis workers, scheduler heartbeat, fail-open Reverb and live/ready probes. The missing layer was a single redacted go-live assessment and an operator-facing runbook/cockpit.

## Findings

- Production is blocked from `schema_admin`, `schema_admin_testing*`, `schema_admin_browser_testing` and the removed development schema. Local/testing guards remain fail-closed.
- `app:bootstrap-local-demo` requires both `APP_ENV=local` and `schema_admin`; production cannot create the synthetic administrator or demo employees.
- Runtime image uses `www-data`, compiled Vite assets and writable `storage`/`bootstrap/cache`; `.dockerignore` excludes `.env`, tests, docs, TXT, logs and test results.
- The production Compose overlay now fail-fast requires the HTTPS `APP_URL`, maps the single separate database name into both runtime and release-governance config, and explicitly enforces Redis cache/queue/session plus encrypted secure cookies. This closes inherited-local-default risk.
- Nginx serves only `/public`, denies dotfiles, applies rate/security headers and forwards PHP to the private app service. HSTS remains deployment/TLS-owned.
- Scheduler has one non-business heartbeat command. Queue/Horizon/Reverb processes are explicit Compose services; Reverb remains non-critical.
- Tracked browser screenshots are retained as governance evidence but excluded from the runtime image.
- Cloudflare Images remains externally blocked by prior live HTTP 403 / code 5403 evidence. Production DB, Redis, mail, TLS/domain and backup target are not verified here.

Classification: **PRODUCTION_BLOCKED_EXTERNAL**. This is not a code failure and is not a production-ready claim.
