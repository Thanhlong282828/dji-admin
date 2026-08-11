# Enterprise Architecture Governance Design

The canonical deployment unit is a Laravel modular monolith. Module manifests declare build/runtime ownership dependencies; direct `use Modules\...` imports are observed independently. Fitness functions fail on cycles, undeclared direct imports, governed Domain-to-Presentation/HTTP coupling, governed controller persistence mutation, or external mutation outside approved adapters.

Enterprise governed modules are Workflow, Notification, IAM, Integration and Audit. Mature bounded contexts—Company, Organization, Employee, Attendance, Leave, Recruitment, Onboarding, Performance and Payroll—adopt the same direction for new work, while recorded legacy transport coupling is removed when the affected feature is changed. Skeleton/catalog modules are not forced into CQRS without active product scope.

Canonical direction is Presentation → Application → Domain, with Infrastructure implementing contracts. CQRS is the orchestration boundary: writes use commands/handlers; reads use queries/read services and must not mutate business state. Domain services remain valid. HTTP request/response, Blade and route state do not enter governed Domain code.

`operations:architecture-audit` and `/admin/operations/architecture` expose the same aggregate, PII-free snapshot. The command/UI execute no mutation. The checksum excludes `generated_at` only indirectly through the whole current snapshot evidence and is evidence of that generated audit, not a release-stable contract hash.

Architecture changes require: manifest edge review, ownership check, static fitness tests, repository PHPStan, focused/full regression proportional to impact, and canonical documentation. No new infrastructure service or database is introduced for governance.
