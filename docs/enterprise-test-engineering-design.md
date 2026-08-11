# Enterprise Test Engineering Design

`TestQualitySnapshotService` scans source-controlled test artifacts and returns typed, checksummed, non-sensitive metadata. `CriticalInvariant` maps risk-bearing behavior to concrete tests. `SafeTestDatabase` accepts only testing-scoped names. `KnownTestWorkerMatcher` recognizes only this repository's worker path. None depends on business repositories.

`operations:test-readiness` checks environment/database safety, connectivity, migrations, orphan workers, Playwright/Chrome and Docker. It never creates a database, migrates, kills a process, or prints credentials. `scripts/ci/check-test-processes.sh` only fails when a known project worker survives.

Execution tiers are static/architecture, fast application, domain regression, serial real-MySQL concurrency, isolated exact-viewport browser, and release full regression. The UI is read-only and deliberately has no run-test endpoint.
