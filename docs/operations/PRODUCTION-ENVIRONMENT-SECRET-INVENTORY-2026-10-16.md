# Production Environment and Secret Inventory — 2026-10-16

## Scope and safety

This is a names-only inventory derived from actual `config/*`, module config and
`.env.example` consumers. No value was copied into this report. A local
`PRESENT` result means only that the name exists locally; it is not proof that
the value is valid or approved for production.

Production `.env` mutation, Secret Manager creation/versioning and credential
transfer remain unapproved. The active production `APP_KEY`, database password
and provider credentials must be preserved.

## Secret delivery decision

Preferred production delivery is Google Secret Manager with least-privilege
access granted to the deployment identity for an explicitly approved manifest.
Codex may materialize a protected temporary environment file through IAP,
validate names/config parsing, and atomically replace the shared production
`.env` only after separate approval. The entire local `.env` must never be
copied. Staging receives generated credentials and no production provider or
Supabase write credentials.

## Required/core runtime

| Variables | Function | Expected source | Production state |
|---|---|---|---|
| `APP_KEY` | Laravel encryption/session identity | existing protected production config | PRESERVE; NOT VERIFIED |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection | existing protected production config | PRESERVE; NOT VERIFIED |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_USERNAME`, `REDIS_PASSWORD` | cache/queue backend | production Redis configuration | NOT VERIFIED |
| `QUEUE_CONNECTION`, `HORIZON_ENABLED` | async runtime selection | reviewed non-secret deployment config | NOT VERIFIED |

## Provider and feature inventory

| Variables | Function | Expected production source | Local audit |
|---|---|---|---|
| `SUPABASE_STORAGE_ENDPOINT`, `SUPABASE_STORAGE_REGION`, `SUPABASE_STORAGE_BUCKET` | private document object storage location | reviewed production configuration | PRESENT, production suitability NOT VERIFIED |
| `SUPABASE_STORAGE_ACCESS_KEY_ID`, `SUPABASE_STORAGE_SECRET_ACCESS_KEY` | server-side S3 authorization | dedicated production Secret Manager versions | PRESENT, production suitability NOT VERIFIED |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | generic S3 disk when enabled | dedicated provider configuration/secrets | endpoint missing locally; remaining names PRESENT but NOT VERIFIED |
| `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN` | zone security API | least-privilege Cloudflare production token | PRESENT, NOT VERIFIED |
| `CLOUDFLARE_DDOS_MONITOR_ENABLED`, `CLOUDFLARE_UNDER_ATTACK_AUTO_ENABLE` | DDoS monitoring/Under Attack automation | reviewed production feature flags | PRESENT, NOT VERIFIED |
| `CLOUDFLARE_IMAGES_ACCOUNT_ID`, `CLOUDFLARE_IMAGES_API_TOKEN`, `CLOUDFLARE_IMAGES_DELIVERY_HASH`, `CLOUDFLARE_IMAGES_TIMEOUT` | image delivery | Cloudflare production account/token | timeout missing locally; remaining names PRESENT but NOT VERIFIED |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | outbound mail | production mail provider and Secret Manager | PRESENT, NOT VERIFIED |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | WebSocket broadcasting | production Reverb config/secret | PRESENT, NOT VERIFIED |
| `GOOGLE_LOGIN_ENABLED`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth | approved OAuth application | MISSING locally; declared in example |
| `FACEBOOK_LOGIN_ENABLED`, `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI` | Facebook OAuth | approved OAuth application | MISSING locally; declared in example |
| `DOCUMENT_STORAGE_DISK`, `EMPLOYEE_DOCUMENT_STORAGE_DISK` | tenant document storage selection | reviewed non-secret production config | PRESENT, NOT VERIFIED |
| `DOCUMENT_CLAMAV_BINARY`, `DOCUMENT_CLAMAV_DATABASE_DIRECTORY`, `DOCUMENT_CLAMAV_TIMEOUT_SECONDS`, `DOCUMENT_CLAMAV_MAX_SIGNATURE_AGE_HOURS` | fail-closed malware scanning | immutable runtime/remote scanner config | database directory and max age missing locally; defaults declared |
| `DOCUMENT_RECONCILIATION_SCHEDULE_ENABLED` | bounded tenant reconciliation scheduler | reviewed production feature flag | MISSING locally; declared in example |
| `OPERATIONS_ALERT_CHANNEL`, `LOG_SLACK_WEBHOOK_URL` | operations notification routing | approved alert destination/secret | MISSING locally |

## Payment and webhook boundary

No active source consumer was found for global Stripe, PayPal, MoMo or VNPay
environment credentials. Commerce payment records currently represent internal
ERP evidence. Integration webhook secrets are per-connection configuration in
the canonical Integration domain; a global `WEBHOOK_SECRET` is not part of the
current multi-company contract. No provider credential should be invented or
provisioned without a real source consumer and separate approval.

## Staging configuration used in Batch 2

Staging used a generated application key and generated MySQL credentials;
mail was logged, broadcasting was null, queue workers/Horizon/scheduler were not
started, OAuth and Cloudflare automation were disabled, documents used local
storage, and no Supabase/AWS/Cloudflare/mail provider credential was supplied.
Only the approved age identity was transferred temporarily for restore.

## Production approval manifest still required

Before production deployment, approve each exact variable name with:

1. source class (existing production value, newly issued provider credential,
   or reviewed non-secret config);
2. destination (`shared/.env` and/or Secret Manager resource);
3. deployment identity allowed to read it;
4. validation method that does not expose the value;
5. rollback version/resource;
6. whether a provider connectivity test is authorized.

Until that manifest is approved, all production secret mutation remains
`NOT AUTHORIZED`.

## Proposed Secret Manager manifest and least privilege

This section specifies names and access boundaries only. Resource names and
values must be approved and created separately.

| Secret variable(s) | Provider/owner | Minimum external permission | Secret reader | Target |
|---|---|---|---|---|
| `APP_KEY` | Laravel application | none | deployment identity only; preserve existing version | protected shared production `.env` |
| `DB_PASSWORD` | production MySQL | DB user limited to the Laravel schema/runtime grants | deployment identity only | shared `.env`; never staging |
| `REDIS_PASSWORD` | production Redis | application cache/queue namespace only | deployment identity only | shared `.env` |
| `MAIL_PASSWORD` or `POSTMARK_API_KEY` / `RESEND_API_KEY` | approved mail provider | send from approved domain only | deployment identity only | shared `.env` |
| `REVERB_APP_SECRET` | Laravel Reverb | application-specific signing only | deployment identity only | shared `.env` |
| `SUPABASE_STORAGE_ACCESS_KEY_ID`, `SUPABASE_STORAGE_SECRET_ACCESS_KEY` | private Supabase S3 bucket | scoped to `laraveldji-media` object operations required by server (`Head/Get/Put/Delete`; list only when reconciliation is approved) | deployment identity only; PHP receives materialized env | shared `.env`; never browser/staging |
| `CLOUDFLARE_API_TOKEN` | Cloudflare zone security | zone security analytics read plus zone settings read/edit only for the approved zone, because runtime reads L7 DDoS events and may set `security_level=under_attack` | deployment identity only | shared `.env` |
| `CLOUDFLARE_IMAGES_API_TOKEN` | Cloudflare Images | Images read/write for the approved account only | deployment identity only | shared `.env` |
| `GOOGLE_CLIENT_SECRET` | Google OAuth | approved OAuth client and exact production redirect URI | deployment identity only | shared `.env` |
| `FACEBOOK_CLIENT_SECRET` | Meta OAuth | approved app and exact production redirect URI | deployment identity only | shared `.env` |
| `SLACK_BOT_USER_OAUTH_TOKEN` or `LOG_SLACK_WEBHOOK_URL` | approved alert channel | post only to approved operations destination | deployment identity only | shared `.env` |

Associated non-secret values such as endpoints, region, bucket, zone/account ID,
redirect URI, feature flags and timeouts belong in a reviewed deployment config
manifest. They should not be stored as secrets merely to avoid review.

The staging service account receives none of the provider secrets above. For
production materialization, grant `roles/secretmanager.secretAccessor` only on
the exact approved secret resources to a short-lived deployment identity; do
not grant project-wide access to the VM or application service account. Revoke
temporary access after atomic `.env` installation and retain the prior secret
versions for rollback.
