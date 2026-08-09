# Database unavailable

- Symptoms: readiness 503; liveness remains 200; business requests are unavailable.
- Verify: MySQL container/service health, connection capacity, locks and storage.
- Safe mitigation: restore the authoritative database service; never point local/testing at `schema_admin`.
- Rollback: rollback the database/network deployment; do not rollback migrations destructively.
- Recovery: readiness 200, migration inventory unchanged, integrity checks pass.
- Escalate: corruption, replication divergence or possible data loss.
