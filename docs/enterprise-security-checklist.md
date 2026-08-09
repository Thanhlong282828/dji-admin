# Enterprise Security Operations Checklist

## Automated gates

- `composer audit --no-interaction` and `npm audit --omit=dev --audit-level=high` block known high-impact runtime dependencies.
- Tracked-file secret scanning blocks common private keys and provider token formats without printing environment values.
- Trivy blocks HIGH/CRITICAL container findings; PHPStan, Pint, PHPUnit and Vite must pass before image publication.
- CI uses GitHub Environment-scoped secrets. Production approval and branch protection are external repository controls that must be enabled in GitHub.

## Application and edge

- Environment/database guard prevents local or testing runtimes from connecting to protected production database names.
- Auth routes are rate limited; API/webhooks are throttled; admin route governance tests require authentication, verification and authorization.
- Nginx provides CSP, clickjacking, MIME sniffing, referrer and permissions headers and hides PHP/Nginx versions.
- Request and error logs exclude request bodies, passwords, tokens, salary, tax, bank values, credentials and private paths.

## Release checklist

Confirm encrypted HTTPS termination, trusted proxy configuration, production `APP_DEBUG=false`, key ownership, secret rotation, least-privilege database users, protected GitHub environments, immutable image digest, backup freshness, rollback image, health readiness, queue failures and read-only production baseline.

Production Compose additionally refuses to render without `APP_KEY`, application DB username/password, and MySQL root password. This detects missing secret injection early while keeping actual values outside Git.
