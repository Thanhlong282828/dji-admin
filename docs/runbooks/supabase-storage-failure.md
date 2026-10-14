# Supabase private document storage failure runbook

## Scope and safety

This runbook covers Enterprise and Employee Documents on the private `supabase` disk. Never print credentials, signed URLs, payloads, original private object keys, or object contents. Do not switch to another disk after metadata names its provider. Production object writes/deletes, bucket changes, RLS changes, credential rotation, migrations and reconciliation deletes require separate approval.

## Triage

1. Correlate the application request ID with structured `document_storage` logs. Use only operation, provider, safe tenant hash, outcome, error classification and duration.
2. Inspect `document_storage` in the observability snapshot. Important signals are scanner, upload, head, signed URL and cleanup failures plus reconciliation anomalies.
3. Classify provider responses: 403 credentials/authorization, 404 metadata drift, 415 MIME contract, 429 throttling, 5xx provider incident, timeout/transport connectivity.
4. For scanner failure, keep uploads fail-closed. Verify the configured binary, signature database freshness, PHP-worker execution permission and bounded timeout. Do not disable scanning as recovery.
5. For cleanup failure, retain the original application error, open an incident and run tenant-scoped reconciliation in dry-run mode. Never delete from a reconciliation report automatically.

## Safe reconciliation

Verify the environment and database first, then run one company at a time:

```bash
php artisan documents:storage-reconcile --company-id=123 --limit=500 --max-seconds=30
```

The command is always dry-run, emits hashed references only, is count/time bounded and returns `1` when anomalies exist. A provider scan failure is an anomaly, not evidence that unenumerated objects are orphans.

## ClamAV deployment checklist

- Production selects the `clamav` scanner from `APP_ENV=production`; do not invent `DOCUMENT_MALWARE_SCANNER` or bypass the module config.
- `DOCUMENT_CLAMAV_BINARY` / `EMPLOYEE_CLAMAV_BINARY` point to an executable available to PHP and queue workers.
- `DOCUMENT_CLAMAV_DATABASE_DIRECTORY` / `EMPLOYEE_CLAMAV_DATABASE_DIRECTORY` point to the readable signature directory. It must contain at least one `.cvd` or `.cld` file.
- Update signatures outside request handling with the OS/package mechanism or FreshClam. The application never downloads signatures during an upload.
- Keep `DOCUMENT_CLAMAV_MAX_SIGNATURE_AGE_HOURS` bounded. Deployment must fail readiness when the newest signature is older than this limit.
- Timeout values are positive and bounded operationally.
- Signature updates are scheduled and monitored.
- Safe deployment validation proves CLEAN, INFECTED, ERROR, TIMEOUT and UNAVAILABLE behavior without uploading malware to Supabase.
- Upload remains unavailable when the scanner cannot return a definitive result.

Local automated tests prove the integration contract only. Production ClamAV runtime remains `NOT_VERIFIED` until checked on the actual PHP worker.

## VPS deployment gate

Run from the release directory after production secrets have been injected and before traffic is switched:

```bash
APP_ENV=production scripts/operations/document-storage-vps-preflight.sh
```

The preflight is read-only. It verifies Supabase configuration presence without printing values, ClamAV executable/signature freshness and tenant-scoped reconciliation configuration. It must not upload, download or delete an object.

Scheduled reconciliation is opt-in. When `DOCUMENT_RECONCILIATION_SCHEDULE_ENABLED=true`, the scheduler reads only active canonical companies in bounded ID-cursor batches and invokes reconciliation separately for each company. Do not maintain a comma-separated tenant list for large installations. Keep `DOCUMENT_RECONCILIATION_COMPANIES_PER_RUN` between 1 and 100 and `DOCUMENT_RECONCILIATION_BATCH_MAX_SECONDS` between 30 and 300. Every invocation remains dry-run and company-scoped. The scheduler service/timer must be active, Redis/shared cache must preserve the cursor and multi-node deployments require the shared cache lock used by `onOneServer()`.

With the default batch size of 25, 1,000 active companies form 40 cursor batches. The five-minute scheduler starts the next bounded batch only after the overlap lock is free. A slow provider may extend the full cycle, but it cannot turn the operation into an unbounded single process.

For native Debian VPS deployment, install `clamav` and `clamav-freshclam`, populate `/var/lib/clamav`, and grant the PHP/queue service user read/execute access. For the canonical Docker runtime, the image packages `clamav-scanner` and `clamav-db`; rebuild the image when the packaged database changes or mount a separately maintained read-only signature volume.

## Alerting and recovery

Alert definitions live in `config/operations.php`. The application evaluates storage failures, scanner failures, cleanup failures, signed URL failures and reconciliation anomalies. If no external alert destination is configured, dashboard evaluation is available but live delivery is `BLOCKED`.

Retry only after the classified dependency has recovered. Upload keys are random and version metadata is transactionally serialized. A failed metadata commit attempts exact-object cleanup; cleanup failure is reported separately and must be reconciled. Never retry by overwriting a known key.

## Rollback

Rollback application code/config only. Do not change the private bucket, ACL-neutral adapter contract, RLS, policies or server credential boundary. Preserve existing immutable metadata and objects. If storage is unhealthy, fail closed and restore the last known application release; do not silently move documents to a different provider.
