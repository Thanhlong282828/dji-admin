# Phase 3F-11 — Production-like Release Certification

## Candidate evidence

- Git SHA: `280043f58be7481dedc9daa1c8927bc4bc60635e`
- Candidate image: `laraveldji-admin:280043f58be7481dedc9daa1c8927bc4bc60635e`
- Candidate digest: `sha256:5b286a8efedc667496d04fbe09e0f184efa1b669b9cd82cf3ee0e30cc08007c5`
- Source state: `working_tree`; source checksum `a1531a41c2bba6a1e0a9c5fedc789a8ac8d8d5f32035e2249ef218cd64c520cd`
- Rollback image: `08903359b58b7dc58b7c520ece59a7f65efa0ddf`, digest `sha256:9dadc464bc4472d5ab17eb7531609d8962b4793b92e31c9123fa045c7cb875ca`
- Migration inventory: 61; checksum `2f2c2f15a6d43a1cfbb32b88ef0c291e825678bb562933f81f8bbeb1bc2425d9`
- Successful manifest checksum: `2c0dc88db5a90a9d45b7d819e862782a049c0b2244d2f395a410b5d74aa8581f`
- Historical failed certificate checksum: `d4b2ead9f76f057115dd939a4ffa309c3c6467c600f59d827dae0aaf395fb676`
- Successful certificate checksum: `fdddffdd715d39fb28119da843aabe982d5feb481953a670f98852b3d004f1bc`
- Latest certificate result: **CERTIFIED**.

## Passed evidence

- Immutable image build/history and non-root runtime; no `.env`, private artifact, Docker socket or privileged container.
- Full migration pretend/review and isolated migration to 61; release-check all true under healthy dependencies.
- Live/ready/metrics-readiness 200 after startup and after recovery.
- Queue worker/Horizon stop, one queued probe observed, restart and backlog returned to zero; failed jobs zero.
- Scheduler process, schedule inventory and fresh heartbeat.
- Blue 200 → green 200 → blue 200 via local proxy, with only the main topology running worker/scheduler.
- Redis-down live 200, ready 503 and metrics-readiness 503; `/login` remained 200. Recovery returned 200/200/200. Release-check returned structured fail exit 1 while down and all-true PASS after recovery.
- Exact browser 1280×800 and 390×844; overflow, console warning/error and page error all zero; latest certified evidence and working-tree source state rendered safely.
- Full PHPUnit 411 tests, 410 passed, 4,367 assertions, one skipped, exit 0. PHPStan 0; Pint/Blade/Vite/Compose/audits/scans pass.
- Isolated containers, network and volumes removed; browser fixture rows zero.

## Historical failed evidence

The first immutable failed certificate remains in storage and was not overwritten. It documents the original Redis-dependent liveness regression. The corrected working-tree candidate generated a separate manifest/certificate after every required gate passed.

No production deployment, migration, backup, rollback, load, fixture, external traffic or business mutation occurred.

Phase 3F-11 is **COMPLETED** for production-like testing certification. A real production release still requires committing the working tree and generating a committed-source manifest/image.
# Phase 3F-12 compatibility

Certification tooling consumes runtime configuration evidence through the release manifest. A changed non-secret snapshot is traceable; environment-specific values are not required to match across production and development.

Secret certification is presence/readiness only. A PASS never exposes or fingerprints the underlying value.
