# Architecture Testing Governance

- Unit architecture tests: graph cycles/edges, layer direction, CQRS controller mutation, adapter boundaries, ADR presence and safe snapshot structure.
- Feature tests: operations route auth/permission/payload and command registration/output.
- Security tests: route/permission/tenant and controlled boundary regression.
- Concurrency tests: lock/idempotency behavior only; architecture read models need no artificial races.
- Browser tests: dashboard/navigation/accessibility exact viewports.
- PHPStan/Pint/Blade/Vite/Composer/npm/Docker: type, format, render, asset, dependency and deployment contracts.

CI must run architecture tests with the normal PHPUnit gate. Fitness functions use stable manifests/namespaces and do not inspect timing or business rows.
