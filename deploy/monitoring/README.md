# Production monitoring deployment contract

LaravelDji production already uses Google Cloud Ops Agent and Cloud Monitoring. The dashboard JSON in `gcp/` extends that canonical stack; it does not install a second Prometheus/Grafana control plane.

## Required evidence before apply

- verify the GCP project, VM instance ID/zone, Cloud SQL resource and Ops Agent health;
- export existing dashboards and alert policies for rollback;
- validate JSON with `jq` and create/update through an identity limited to Monitoring Dashboard Editor;
- reuse the existing notification channel by resource name; never store an email address, webhook or credential in this repository;
- keep Prometheus, Grafana and Horizon private. Horizon is optional and must replace, not accompany, `queue:work` for the same queue.

## Alert acceptance

Production policies must use sustained durations and recovery notifications: VM CPU 70%/5m, RAM 75%/5m, disk 70%/5m, Cloud SQL CPU/disk 70%/5m, HTTP 5xx 5%/5m, p95 1500ms/5m, queue depth 100/10m, oldest job 300s/10m, failed jobs >=1/10m, scheduler stale 180s/5m, and exporter/host absence. Threshold changes require measured capacity evidence.

Application metrics are permission-protected snapshots. To alert in Cloud Monitoring, emit only bounded structured counters through the approved log/metric pipeline; never attach URL parameters, request bodies, user IDs, object keys, tokens or exception text as metric labels.

## Application metric export

`operations:export-metrics` runs once per minute under the canonical Laravel scheduler, with `onOneServer()` and `withoutOverlapping(5)`. It performs one bounded Cloud Monitoring `createTimeSeries` request containing at most 20 series. Business requests only update local cache counters; they never call Google Cloud.

Enable only after the descriptor file has been provisioned in the target staging project and the VM service account has `roles/monitoring.metricWriter`:

```dotenv
OPERATIONS_METRICS_EXPORT_ENABLED=true
OPERATIONS_METRICS_EXPORT_TIMEOUT_SECONDS=5
```

The exporter reads its short-lived token and `gce_instance` identity from the authenticated GCE metadata service. No service-account key file is accepted. Export failures are redacted, recorded locally and return a failed scheduler command without changing business responses.

Metric semantics are defined in `gcp/laraveldji-metric-descriptors.json`. Values are bounded-window gauges, not monotonically increasing billing counters. Expected ingestion delay is one scheduler interval plus normal Cloud Monitoring ingestion latency. Queue depth and oldest-age series are omitted when Redis inspection is unavailable; `queue/available=0` distinguishes that state from a true zero backlog.

`gcp/laraveldji-alert-policies.json` is a valid policy template. Before provisioning, replace `__NOTIFICATION_CHANNEL_RESOURCE__` with the already-approved notification-channel resource name and validate every policy with the Cloud Monitoring API. Never commit an email address or webhook URL. Custom metric descriptors and alert ingestion remain `NOT_VERIFIED` until an explicitly approved staging write test succeeds.

## Rollback

Delete only the newly created dashboard/policies by recorded resource name or restore the exported prior JSON. Dashboard rollback must not restart Laravel, Redis, MySQL, workers or the VM.
