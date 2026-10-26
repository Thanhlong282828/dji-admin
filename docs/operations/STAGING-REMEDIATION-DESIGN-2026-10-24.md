# Staging remediation design — 2026-10-24

## Scope and release identity

This is preparation evidence only. No VM was started, no GCP/IAM/network/package setting changed, no image was pulled, and no database, migration, seeder, restore or deployment was executed.

- Release: `a8826de82e7dbc3e1e81963d22439b83af604bfb`
- Approved image: `ghcr.io/thanhlong282828/dji-admin:a8826de82e7dbc3e1e81963d22439b83af604bfb@sha256:c4d82ed9ffe8dacb53479eee790e08bb25815df2f9bd0e07de8292910b9f6eca`
- Prepared branch: `codex/staging-remediation-design-20261024`
- Baseline: GitHub default branch at the release SHA above.

## Selected minimum topology

Use the existing dedicated, no-public-IP staging VM with Docker Compose. Run MySQL 8.4, Redis, application, queue, scheduler and Nginx on that VM using staging-only named volumes. This avoids sharing Cloud SQL production and avoids always-on managed MySQL/Redis cost. Reverb remains an opt-in profile. The default ingress remains loopback/IAP for infrastructure rehearsal; full browser acceptance requires an approved HTTPS endpoint.

The topology is appropriate only for bounded staging verification. It is single-host, non-HA, has no zero-downtime guarantee and must never be promoted as the production topology.

## Dependency and proposed GCP changes

| Dependency | Existing evidence | Proposed change requiring separate approval | Purpose |
|---|---|---|---|
| Compute VM | `laraveldji-staging-rehearsal-20261015`, stopped, e2-medium, 30 GB disk | Start only for an approved window; consider a separate persistent data disk | Isolated container host |
| VPC/subnet | `laraveldji-staging-net`, `10.60.0.0/24`, no public IP | No topology change | Production isolation |
| Outbound | Router exists; no NAT | Add narrowly scoped Public NAT for the staging subnet, or approve a controlled image mirror/transfer | GHCR and required package/signature egress |
| Ingress | IAP SSH firewall only | Option A: IAP tunnel for non-browser rehearsal. Option B: HTTPS load balancer/proxy, staging DNS and certificate | Health-only versus full browser acceptance |
| Database | Production Cloud SQL also contains non-production schemas | Do not use it. Use Compose MySQL with staging-only user and volume | Absolute production DB/user separation |
| Redis | No managed Redis evidence | Use Compose Redis with dedicated volume/prefix | Cache, session and queue isolation |
| Secrets | Only a test secret was inventoried; staging service account had no project role evidence | Create staging-only secret set and least-privilege delivery path; never reuse production values | Runtime and GHCR pull authentication |
| Storage | No staging bucket; production/private buckets exist | Use staging-only local persistent volume for this bounded topology, with encrypted off-VM backup; create a staging bucket only if external object storage is required | Document isolation and recovery |
| Backup | No staging snapshot or restore evidence | Attach snapshot policy if approved; create database/storage backups and prove isolated restore | Rollback and recovery |
| Observability | VM scopes include logging/monitoring but no IAM role was observed | Grant only required writer roles if the approved logging design needs them | Logs and health evidence |

## Cost impact

No new managed MySQL, Redis or object-storage service is proposed. The existing stopped VM incurs compute cost only while running; exact e2-medium pricing depends on the current Singapore SKU and discounts and must be confirmed in the Google Cloud calculator before approval. The existing 30 GB balanced persistent disk remains billable while retained. Using the published Singapore balanced-disk rate of USD 0.00013699/GiB-hour gives an indicative USD 2.96 per 720-hour month for 30 GiB, excluding snapshots.

Public NAT for one assigned VM is approximately USD 4.61 per 720-hour month before traffic: `(0.0014 + 0.005) × 720`, plus USD 0.045/GiB processed, internet egress and logging. A time-bounded NAT window reduces hourly charges but remains a separately approved network mutation. HTTPS load-balancer, certificate/DNS, snapshots, logging and outbound traffic are additional and not estimated here because no target design or usage volume has been approved.

Pricing basis: official Google Cloud Compute disk and Cloud NAT pricing pages, checked on 2026-10-24. Estimates are not a billing quote.

## Ingress decision

### Option A — private IAP rehearsal

- Lowest exposure and no public application ingress.
- Supports image/runtime, migrations, CLI checks and tunneled health verification.
- Does not certify secure browser cookies, OAuth redirects, WebSocket/TLS behavior or real external reverse-proxy headers.

### Option B — HTTPS staging

- Required for authoritative desktop/mobile browser acceptance and secure-cookie behavior.
- Requires staging DNS, TLS, an approved reverse proxy/load balancer, exact `TRUSTED_PROXIES` and an ingress firewall limited to the proxy path.
- Adds cost and attack surface; production domain, certificate and secrets remain forbidden.

## No-NAT image delivery design

When direct GHCR egress is not approved, use a trusted transfer host with a temporary pull-only GHCR credential. Pull and verify the exact digest there, export an OCI archive, create a SHA-256 checksum, transfer archive plus checksum through IAP, and verify checksum, architecture, OCI revision/source and digest again before loading it on the VM. Delete the archive after verification. The credential never reaches the VM. This report does not authorize the transfer, IAP access or any VM action.

Named Compose volumes use the Docker data root unless a separately mounted data disk is configured. If the VM boot disk has auto-delete enabled, deleting the VM also deletes those volumes. Approval therefore requires explicit disk identity/auto-delete evidence, off-VM encrypted backups for MySQL, `staging_storage` and `release_manifests`, plus an isolated restore rehearsal.

## Migration inventory

The static scanner reports 127 migrations: 2 safe, 114 caution and 11 blocking/manual-review. “Blocking” means review is mandatory; it does not prove the migration is unsafe. Pending migrations must be recalculated against the actual staging database before execution.

| Migration | Trigger | Required review |
|---|---|---|
| `2026_07_23_120000_expand_companies_for_enterprise_admin.php` | data update | Row count, tenant defaults, lock/batch behavior |
| `2026_07_24_120000_complete_employee_core_security_fields.php` | raw SQL | ENUM conversion compatibility and truncation risk |
| `2026_07_24_130000_complete_recruitment_onboarding_core.php` | raw SQL | Existing status values and lifecycle compatibility |
| `2026_07_26_000009_align_leave_request_workflow_schema.php` | raw SQL | Legacy leave status mapping and locking |
| `2026_07_26_000010_create_payroll_engine_foundation.php` | raw SQL | Payroll enum/state compatibility; no immutable snapshot rewrite |
| `2026_07_29_000021_complete_payroll_adjustment_downstream_schema.php` | raw SQL | Adjustment state mapping and accounting immutability |
| `2026_08_13_120100_add_demotion_employee_history_event.php` | raw SQL | Employee history ENUM compatibility |
| `2026_08_29_000080_scope_seller_profiles_to_companies.php` | data update | Deterministic tenant mapping and uniqueness |
| `2026_08_31_000086_backfill_product_media_company_scope.php` | data update | Deterministic company ownership and orphan handling |
| `2026_08_31_000087_normalize_local_media_delivery_urls.php` | data update | URL rewrite reversibility and storage ownership |
| `2026_08_31_000088_create_procurement_ap_inventory_valuation.php` | raw SQL | Inventory/AP state constraints, locks and accounting snapshots |

For each pending item: capture pre-counts and distinct legacy values, run on an isolated restored copy, measure duration/locks, reconcile post-counts/checksums, verify negative/tenant paths and document forward-fix versus image rollback compatibility. No migration is approved by this report.

## Patch boundaries

- `docker-compose.staging.yml`: isolated runtime and static assets sourced from the immutable image.
- `scripts/operations/staging-preflight.sh`: fail-closed, non-mutating static validation.
- `scripts/deploy/release.sh` and `scripts/deploy/rollback.sh`: backward-compatible staging overlay selection while preserving production guards.
- `tests/Unit/Operations/StagingDeploymentContractTest.php`: regression protection.
- `docs/runbooks/staging-deployment.md`: reusable execution and rollback rules.
- This report: environment-specific evidence, topology and cost notes.

## Remaining external approvals

GCP egress, VM start, secret creation/access, HTTPS resources, optional persistent data disk/snapshot policy, GHCR credential delivery, staging execution, backup/restore, migration and deployment each remain separate approval gates. The patch does not make the current staging VM deployable by itself.
