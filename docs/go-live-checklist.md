# Go-live checklist

## Automatically verified

- [x] Composer lock/install model and npm lock/build model are reproducible in Docker/CI.
- [x] Fresh schema rehearsal proved 96/96 and 329 tables; additive migrations `000097`–`000098` subsequently bring the governed schema to 98 migrations / 329 tables.
- [x] Config, route and view caches build and the cached application boots.
- [x] Production Vite manifest and referenced assets load.
- [x] Health, security headers, CSRF and unauthenticated behavior pass locally.
- [x] Redis cache/queue/lock and bounded queue probe pass locally.
- [x] DMS private access/SHA regression passes with local storage.
- [x] Backup checksum and disposable restore pass.
- [x] Full quality, architecture, Data Quality, API and dependency gates pass.
- [x] Local/testing DB guard blocks production/local schema confusion.

## Manual required

- [ ] Approve release revision, immutable image digest and previous rollback image.
- [ ] Review pending migrations, table sizes and online-DDL/lock behavior, especially source indexes in `000096`, the payroll journal-line Branch index/FK in `000097`, and Payroll Input Cost Center snapshot columns/index/FK in `000098`.
- [ ] Select exactly one queue manager: default `queue:work`, or opt-in Horizon profile—not both.
- [ ] Install one scheduler owner running every minute and verify heartbeat.
- [ ] Verify filesystem ownership for `storage` and `bootstrap/cache` without world-writable permissions.
- [ ] Confirm mail, Reverb and optional integration failure policies.
- [ ] Perform post-deploy health, read-only smoke and reconciliation monitoring.

## External configuration required

- [ ] Verified HTTPS domain/TLS and trusted proxy behavior.
- [ ] Separate least-privilege production MySQL database and secret-managed credentials.
- [ ] Production Redis authentication/TLS/persistence/prefixes and capacity.
- [ ] Encrypted shared session with secure cookie.
- [ ] Production mail provider delivery verification if required.
- [ ] Backup targets for DB and private/object media, retention and restore operator access.
- [ ] Reverb process/reverse proxy if realtime is enabled.
- [ ] Cloudflare Images runtime verification if selected; it is not a core readiness dependency.
- [ ] Passport/OAuth keys and approved clients where API OAuth is enabled.
- [ ] Approved RPO and RTO.

## Blockers

- [ ] Do not proceed while readiness references a reserved local/testing DB.
- [ ] Do not proceed with `APP_DEBUG=true`, non-HTTPS `APP_URL`, missing `APP_KEY`, unencrypted/non-secure production session, missing backup evidence or unreviewed migration failure strategy.
- [ ] Do not run demo/bootstrap fixture commands or restore over an existing business database.

Final production status remains `PRODUCTION_BLOCKED_EXTERNAL` until every applicable external/manual blocker is verified in the deployment environment.
