# Cloudflare DDoS alert runbook

## Signal

`cloudflare_ddos_events` counts Cloudflare `l7ddos` security-event groups for the configured zone and rolling window. When the threshold is breached, the ERP checks the current zone Security Level and idempotently enables `under_attack`. It never automatically disables the mode.

## Response

1. Confirm the alert in **ITSM · Vận hành nền tảng → Observability**.
2. Open the configured zone in Cloudflare Security Analytics and validate the time window, action and traffic source.
3. Confirm origin health, latency, 5xx rate, Redis, queue and database readiness in the ERP dashboards.
4. Escalate through the incident process if the event count stays above threshold or the origin is degraded.
5. Do not paste the API token into tickets, logs or screenshots.

## Required token scope

Use a Cloudflare API token limited to the intended zone with **Zone Read**, **Analytics Read** and **Zone Settings Edit**. Do not grant unrelated edit permissions.
