# Enterprise production go-live design

`ProductionReadinessService` is a typed, read-only aggregator. It inspects safe configuration metadata, migration inventory, compiled assets, writable runtime paths, health routes, secret readiness metadata and external dependency states. It never prints credential values, connects to a VPS, runs migrations, seeds, deploys or restarts services.

The same snapshot powers `operations:production-readiness --json` and `/admin/operations/go-live`. `operations.view` controls the UI. The output separates application checks from dependencies and uses `CONFIGURED`, `VERIFIED`, `MOCK_ONLY`, `NOT_REQUIRED` and `BLOCKED` precisely. Configured never implies verified.

Certification flow: code → tests → immutable build → release check → migration review → deploy → health → read-only smoke → monitor. Rollback uses a previous immutable image when database/config compatibility is proven; otherwise a reviewed forward fix is required.
