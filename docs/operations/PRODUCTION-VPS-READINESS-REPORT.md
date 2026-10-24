# Production VPS Readiness Evidence

## Evidence boundary

This report contains redacted runtime evidence only. Google Cloud discovery verified the canonical project `project-06b7a0c7-35a4-4282-b81` and running VM `laravel-server` in `asia-southeast1-a`; Compute Engine currently reports external address `136.85.34.188`. This identity came from the Compute API, not from the historical hint.

Direct SSH initially reached a network timeout on port 22, while IAP reached the VM but rejected OS Login user `lychieuthan35_gmail_com` because the encrypted local key was not loaded. After the operator loaded the matching identity into SSH agent, IAP SSH and the read-only runtime audit succeeded; the current evidence is recorded below. No IAM, firewall, SSH key, instance metadata, guest service or production database was changed. The requested gcloud troubleshoot diagnostic created one Network Management Connectivity Test despite being described by installed help as diagnostic-only; this side effect is disclosed below and was not deleted without authorization.

## OS Login and IAP evidence — 2026-10-14

All checks in this section were read-only. No private key content was read or emitted.

| Check | Evidence | Result |
|---|---|---|
| Active gcloud identity | `lychieuthan35@gmail.com` | PASS |
| OS Login POSIX identity | primary username `lychieuthan35_gmail_com`, UID/GID `624683432` | PASS |
| Registered OS Login key | RSA 3072, OpenSSH fingerprint `SHA256:xMxtkUC8aYLX+LtZQpY76HpwSA5hzngVOLbrVtC7uTc` | PASS |
| Local public key | `google_compute_engine.pub` has the same OpenSSH fingerprint | PASS |
| Key expiration | no `expirationTimeUsec` value; the registered key is not expired | PASS |
| Project IAM binding | active account has direct `roles/owner` | PASS |
| Effective Compute/IAP permissions | `compute.instances.get`, `compute.instances.osLogin`, `compute.instances.osAdminLogin`, `iap.tunnelInstances.accessViaIAP` | PASS |
| Attached service-account permission | `iam.serviceAccounts.actAs` | PASS |
| Instance metadata | `enable-oslogin=TRUE` | PASS |
| Guest OS Login signal | serial log shows `google-oslogin-cache.service` successfully refreshed passwd and group caches | PASS, limited evidence |
| IAP transport | tunnel reaches the VM | PASS |
| SSH authentication | `Permission denied (publickey)` for the canonical POSIX username | FAIL |
| Private-to-public derivation | `ssh-keygen -y` requested the encrypted private-key passphrase; no passphrase was supplied or recorded | `CLIENT_KEY_PAIR_NOT_VERIFIED` |
| SSH agent | agent is available but contains no identities | FAIL for non-interactive encrypted-key use |
| gcloud troubleshoot | network/VPC/IAM/VM status/VM boot all reported zero issues | PASS |

### Diagnosis

The evidence excludes the common control-plane causes: POSIX username mismatch, registered-public-key mismatch, expired OS Login key, missing OS Login IAM permission, missing IAP permission, missing service-account impersonation permission, unreachable IAP path, unhealthy VM and failed VM boot. IAP itself is healthy enough to deliver the SSH connection to the guest.

The local `google_compute_engine` private key is encrypted. `ssh-keygen -y` could not derive its public key without the passphrase, and the SSH agent reports no loaded identities. Codex did not request, capture or expose the passphrase. Therefore the private key actually available to the non-interactive SSH client cannot be proven to match `google_compute_engine.pub` and the OS Login profile. This is a concrete client-side blocker and the required classification is `CLIENT_KEY_PAIR_NOT_VERIFIED`.

Cloud Logging and serial output show successful `google-oslogin-cache.service` passwd/group refreshes, but no SSHD authentication decision was available in the permitted log sources. Direct inspection of `sshd -T`, `google_authorized_keys`, PAM/NSS and `google-guest-agent` service state requires a guest shell or another approved management channel. Guest-side SSHD misconfiguration is therefore not proven and must not be reported as the root cause.

Current blocker classification: `SSH_CLIENT_CREDENTIAL_UNAVAILABLE`; evidence state: `CLIENT_KEY_PAIR_NOT_VERIFIED`.

### Troubleshoot side-effect disclosure

`gcloud compute ssh --troubleshoot --tunnel-through-iap` was run after the installed help described diagnostic tests only. It reported:

- network forward path: `REACHABLE`;
- network return path: `REACHABLE`;
- user permissions: zero issues;
- VPC settings: zero issues;
- VM status: zero issues;
- VM boot: zero issues.

The command nevertheless created Network Management Connectivity Test `ssh-troubleshoot-5ikwk`. Cloud Audit Logs record `CreateConnectivityTest` under `lychieuthan35@gmail.com` at `2026-10-14T08:20:54Z`. No `serviceusage.services.enable` event was found in the inspected time window; Network Management and Monitoring APIs were enabled when checked after the diagnostic. The connectivity test still existed at the read-only post-check. It was not deleted because deletion was not authorized. No further `--troubleshoot` invocation is allowed without explicit approval covering this side effect.

### Remediation requiring separate approval

The following commands are proposals only and were **not** executed.

1. Load the existing encrypted key into the local SSH agent interactively. The operator must enter the passphrase locally and must not send it to Codex:

   ```bash
   ssh-add "$HOME/.ssh/google_compute_engine"
   ssh-add -l -E sha256
   ```

   The loaded fingerprint must equal `SHA256:xMxtkUC8aYLX+LtZQpY76HpwSA5hzngVOLbrVtC7uTc`. Only after that match is confirmed should the existing IAP SSH command be retried once.

2. If the loaded fingerprint differs, stop. With separate approval, inspect/repair the local key file or register the intended public key. Do not remove the current OS Login key until recovery access is confirmed.

3. If a matching loaded key is still rejected and an already-authorized alternate guest-access method is available, inspect the guest without changing it:

   ```bash
   getent passwd lychieuthan35_gmail_com
   systemctl status google-guest-agent google-oslogin-cache ssh sshd --no-pager
   sudo sshd -T | grep -Ei 'authorizedkeys(command|commanduser|file)|usepam'
   sudo /usr/bin/google_authorized_keys lychieuthan35_gmail_com
   sudo journalctl -u ssh -u sshd -u google-oslogin-cache --since '-30 minutes' --no-pager
   ```

4. If guest lookup is healthy but the intended public key is absent from OS Login, refresh it only after explicit approval:

   ```bash
   gcloud compute os-login ssh-keys add \
     --project=project-06b7a0c7-35a4-4282-b81 \
     --key-file="$HOME/.ssh/google_compute_engine.pub" \
     --ttl=30m
   ```

5. The diagnostic-created Connectivity Test may be removed only with explicit approval:

   ```bash
   gcloud network-management connectivity-tests delete ssh-troubleshoot-5ikwk \
     --project=project-06b7a0c7-35a4-4282-b81
   ```

At the earlier checkpoint, runtime checks were not attempted because SSH authorization had not succeeded. The subsequent verified audit is recorded below.

## Production VPS runtime evidence — 2026-10-14

The operator loaded the existing encrypted key into the local SSH agent. The loaded identity fingerprint was `SHA256:xMxtkUC8aYLX+LtZQpY76HpwSA5hzngVOLbrVtC7uTc`, matching both `google_compute_engine.pub` and the OS Login profile. IAP SSH then succeeded as OS Login user `lychieuthan35_gmail_com`. No private key or passphrase was read or emitted.

All guest commands in this section were read-only. No package, configuration, service, queue item, alert, deployment, database row or application file was changed.

### Host capacity

| Signal | Observed evidence | Assessment |
|---|---|---|
| OS/kernel | Debian cloud kernel `6.12.107+deb13-cloud-amd64`, x86_64 | VERIFIED |
| Uptime | 26 days, 9 hours | VERIFIED |
| CPU | 2 vCPU; load averages `0.72 / 0.63 / 0.62` | HEALTHY at sample time |
| RAM | 964 MiB total; 586 MiB used; 377 MiB available | LOW CAPACITY, no immediate exhaustion |
| Swap | 4 GiB total; 187 MiB used | ACTIVE |
| Root disk | 20 GiB total; 8.5 GiB used; 46% utilization | HEALTHY |
| Root inodes | 11% used | HEALTHY |
| Network | `ens4` reported zero RX/TX errors and zero drops | HEALTHY at sample time |

This is one point-in-time sample, not proof of historical capacity or SLO compliance.

### Web and PHP runtime

| Check | Evidence | Result |
|---|---|---|
| Nginx | systemd active/enabled; `nginx -t` successful | PASS |
| PHP-FPM | `php8.4-fpm.service` active/enabled; expected sockets present | PASS |
| PHP | `8.4.26`; Laravel `13.27.0` | VERIFIED |
| HTTPS localhost | HTTP 200 | PASS |
| HTTP localhost | HTTP 404 | EXPECTED host/scheme routing behavior |
| Public vhost health | `kayaga.one` and `www.kayaga.one`: `/health/live`, `/health/ready`, `/health/metrics-readiness` all HTTP 200 | PASS |
| Protected observability route | HTTP 302 without authenticated browser session | AUTH BOUNDARY PRESENT; UI content not browser-verified |

### ClamAV and upload-worker compatibility

| Check | Evidence | Result |
|---|---|---|
| `clamscan` | not found | FAIL |
| `clamdscan` | not found | FAIL |
| `freshclam` | not found | FAIL |
| `clamav-daemon` / `clamav-freshclam` | systemd units not found | FAIL |
| ClamD socket | `/run/clamav/clamd.ctl` missing | FAIL |
| Signatures | no runtime signature evidence because ClamAV is absent | FAIL |
| PHP compatibility | `fileinfo`, `sockets` and `proc_open` available | PASS at PHP capability layer |

Production malware scanning is **not ready**. The PHP process has the required basic capabilities, but neither a scanner binary nor daemon/socket/signature runtime exists. Upload behavior must remain fail-closed. No upload was attempted.

### Queue, Redis and Horizon

| Check | Evidence | Result |
|---|---|---|
| Redis | service active/enabled; `PING` returned `PONG` | PASS |
| Canonical queue worker | `laravel-queue.service` active/enabled; one `queue:work redis --queue=default` process | PASS |
| Default backlog | ready `0`, reserved `0`, delayed `0` | PASS at sample time |
| Failed jobs | database count `1` | ATTENTION REQUIRED; record was not modified |
| Horizon | package commands present, `horizon:status` reports inactive | INACTIVE |
| Topology | `queue:work` is active while Horizon is inactive | CONSISTENT with one canonical worker mode |

The architecture permits either `queue:work` or Horizon. Production currently uses `queue:work`. `HORIZON_ENABLED` is absent, so the expected topology is not explicitly documented in environment configuration.

### Scheduler and 1,000-company reconciliation

| Check | Evidence | Result |
|---|---|---|
| Scheduler timer | `laravel-scheduler.timer` loaded, enabled and active | PASS |
| Last scheduler service result | `success`, exit status `0` | PASS |
| Operations heartbeat | registered every minute | PASS |
| Scheduled jobs | employee, attendance, leave, recruitment, IAM, audit, notification and workflow commands listed | VERIFIED |
| Reconciliation command | no `documents:storage-reconcile` or company reconciliation command registered in deployed Artisan command list | FAIL / NOT DEPLOYED |
| 1,000-company source/config reference | bounded batch/capacity reference exists in deployed source/config search | PARTIAL EVIDENCE ONLY |

The deployed runtime does not expose the canonical reconciliation command, so 1,000-company reconciliation cannot be executed or claimed production-ready. No reconciliation job was run.

### Monitoring, logs and Alert Center

| Check | Evidence | Result |
|---|---|---|
| Google Cloud Ops Agent | active/enabled; Fluent Bit and OpenTelemetry collector processes present | PASS |
| Local Prometheus/Node Exporter/Grafana | units/listeners not found | NOT DEPLOYED |
| Laravel health/readiness | all canonical endpoints returned 200 | PASS |
| Observability workspace | permission-protected route exists | PASS at route/auth-boundary level |
| Laravel log window | latest file window spans 2026-09-19 through 2026-10-03 | STALE for current-state inference |
| Historical log counters | 208 error-or-higher lines and 18 HTTP-5xx mentions in the last 5,000 lines of that stale window | HISTORICAL ATTENTION; not a current-rate metric |
| Alert channel | `ALERT_CHANNEL`, webhook, email and cooldown configuration absent | NOT CONFIGURED |
| Alert delivery | no alert sent, as required | NOT VERIFIED |
| VPS monitoring adapter | related endpoint/enabled variables absent | NOT CONFIGURED |

Cloud Ops Agent provides the external metrics collection path required by the architecture, but the Laravel-side backend adapter and alert delivery configuration are absent. Configuration presence cannot be substituted for an end-to-end delivered alert, and no alert was sent during this audit.

| Component | Source | VPS runtime | Alert delivery |
|---|---|---|---|
| CPU/RAM/Disk/Network | Google Cloud Ops Agent plus host counters | Point-in-time HEALTHY; continuous backend exists | NOT CONFIGURED |
| Laravel exceptions/5xx | Existing logging and bounded HTTP metrics | Historical stale-log counters only | NOT CONFIGURED |
| Performance/audit | p50/p95/p99, SLO and activity/audit sources exist | Health endpoints PASS; aggregate UI not browser-verified | NOT CONFIGURED |
| Queue workers | Redis queue and bounded worker | VERIFIED active; backlog 0; failed jobs 1 | NOT CONFIGURED |
| Horizon | Optional profile | INACTIVE; canonical `queue:work` active | NOT CONFIGURED |
| ClamAV | Required fail-closed upload scanner | FAIL: binaries, daemon, socket and signatures absent | NOT CONFIGURED |
| Supabase storage | ACL-neutral adapter and telemetry source ready | Prior storage evidence is separate; no live upload attempted | NOT VERIFIED |
| Scheduler/reconciliation | heartbeat plus 1,000-tenant cursor batching source ready | Scheduler PASS; reconciliation command NOT DEPLOYED | NOT CONFIGURED |
| Dashboard | permission-protected unified observability workspace exists | Route/auth boundary VERIFIED; content not browser-verified | N/A |
| Alert Center | threshold snapshot exists; audited ack/resolution persistence absent | Channel configuration absent | NOT VERIFIED |

Classification: `PRODUCTION_READINESS_PARTIAL`.

SSH/IAP access is now verified. To advance production readiness, an approved operational change must install and configure ClamAV with current signatures, explicitly define the selected queue topology, deploy/register the bounded reconciliation command, configure the Laravel monitoring backend and alert channel, investigate the existing failed job and historical error window, and separately approve an alert-delivery test. Codex must not install packages, restart services, deploy code, mutate production data or send an alert without explicit authorization.
