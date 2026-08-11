# Enterprise Architecture Governance

Phase 3F-20 links architecture fitness functions to the early static test tier. The architecture service remains independent from the Test Quality snapshot to avoid an operations-support dependency cycle.

Phase 3F-19 establishes machine-enforced module, layer, CQRS and controlled-side-effect boundaries for the existing modular monolith. It adds a read-only audit command and protected Operations dashboard, five ADRs and ownership/idempotency/debt registers. It adds no business module and no migration.

Operational surfaces:

- `php artisan operations:architecture-audit --json`
- `/admin/operations/architecture` behind `auth`, `verified`, `operations.view`
- architecture fitness functions under `tests/Unit/Architecture`

The status matrix is canonical. Older documents may retain intermediate PARTIAL evidence and are not rewritten to imply earlier capability.

## Completion evidence

- Graph: 39 modules, 54 declared edges, 39 observed direct-import edges, 0 cycles and 0 undeclared edges. Fifteen declared-unobserved edges are documented runtime/event/schema contracts.
- Governed layers: 0 Domain→HTTP/Presentation, controller CQRS/persistence or external-adapter boundary violations.
- Technical debt: 0 critical, 0 high, 3 medium, 2 low. Five ADRs present.
- Tests: focused architecture/Integration/navigation 43 tests, 42 passed, 1,691 assertions, one skipped; full PHPUnit 468 tests, 467 passed, 4,826 assertions, one skipped, exit 0.
- PHPStan app/Modules and architecture tests level 10: 0. Pint, Blade, Vite, Composer/npm audits, optimized autoload, Swagger generation, secret/CI scans and Docker Compose pass.
- Exact Chrome 1280×800 and 390×844: 17 pages, Architecture/Performance/Swagger/Security Assurance 200, overflow and console/page problems 0; Operations contains 11 children and fixture cleanup is zero.
- No migration. All four databases remain 61 migrations/230 tables. Production business counts are unchanged.

Phase 3F-19 status: **COMPLETED**.
