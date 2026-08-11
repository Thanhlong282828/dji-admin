# Enterprise Business Continuity Design

## Dependency order

`storage/network → MySQL/Redis → core HTTP → queue/Horizon/scheduler → Reverb/adapters`.

Recovery is sequential. Critical dependencies are restored and verified before asynchronous consumers; only one scheduler/worker authority is enabled during recovery. Outbox consumers resume through existing checkpoint/idempotency guards.

## Backup inventory

Must backup: MySQL schema/data, private artifacts and integrity metadata, release manifests/certificates, non-secret critical configuration references. Rebuildable: immutable application image from source/build evidence. Ephemeral: cache, compiled views and temporary files.

## RTO/RPO

Targets are environment configuration. Evidence records configured target, observed result, measurement scope and source. Deterministic fixture restore observed RPO 0 seconds only because the backup point exactly followed the committed fixture; this is not production RPO.

## Security

Artifacts use private directories and mode 0600. Certificates/logs contain component, duration, counts and checksums only—never credentials, PII, salary, tax, bank data or storage paths. Production dependencies and data are outside the exercise network.
