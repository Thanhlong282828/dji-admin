# Redis unavailable

- Symptoms: readiness 503; liveness remains 200; queue/cache/realtime degraded.
- Verify: Redis healthcheck, container logs, memory and persistence volume.
- Safe mitigation: restore the existing Redis instance/volume; do not switch to an unreviewed cache store.
- Rollback: rollback the infrastructure change that caused loss of connectivity.
- Recovery: `PING` succeeds, readiness returns 200, workers resume pending jobs without duplicate mutation.
- Escalate: persistence corruption or repeated failover.
