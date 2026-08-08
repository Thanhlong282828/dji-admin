# SCHEMA Admin Architecture

SCHEMA Admin is a Laravel 13 modular monolith on PHP 8.4. Bounded contexts live in `Modules/`; `Core` owns cross-cutting contracts such as Command Bus and Query Bus. Business modules own their application, domain, infrastructure and presentation code. Admin is the write authority for HRM and CMS data in MySQL `schema_admin`; Main never reads this database.

Write requests follow Request → Controller → Command Bus → Handler → write repository → model → event. Reads follow Controller → Query Bus → Handler → read repository → Resource/ViewModel. Module repositories are bound through module providers. Redis backs cache, queues, locks and rate limits. Horizon supervises categorized queues; Reverb provides authorized private-channel realtime events. Cloudflare Images is accessed behind a media storage contract, with local storage for development.

Public integration is REST `/api/v1` with Sanctum where authentication is needed. Spatie Permission provides RBAC and Spatie Activity Log records auditable mutations. MySQL data is tenant-scoped by company/organization where applicable. Sensitive HR and payroll fields require policy checks, masking and encryption.

Docker uses multi-stage Composer, Vite and PHP-FPM builds behind Nginx. GitHub Actions performs formatting, static analysis, tests, audits, image builds and compose validation. Staging and production deployments remain disabled until server credentials and protected environments are configured.

Future extraction candidates are payroll, media processing and external integrations. Extraction must preserve module contracts and publish versioned integration events; the current architecture intentionally remains a modular monolith.

## Company bounded context

Company is the first complete HRM bounded context. Web/API presentation dispatches immutable commands and queries through Core buses. Read and write repositories are separate. List cache keys use `schema:admin:company:list:{hash}` with a version stored at `schema:admin:company:version`; dashboard stats use `schema:admin:company:stats`. Domain-event listeners increment only the Company cache version and forget Company/dashboard stats—never `Cache::flush()`.

Company authorization uses `CompanyPolicy`, Spatie permission middleware and `@can`. Mutations capture the authenticated actor server-side, run through a transaction, dispatch a domain event, write Activity Log and invalidate cache. The Media module is not active, so `logo_media_id` remains nullable without a foreign key until Media owns a stable table.
