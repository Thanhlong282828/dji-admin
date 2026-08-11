# Backup and Restore Runbook

## Strategy

- Database: encrypted, single-transaction MySQL logical backup daily; weekly restore verification; point-in-time recovery through provider binlogs where available.
- Private artifacts: provider-side versioning plus lifecycle retention; store manifests and SHA-256 checksums separately.
- Keep 30 daily, 12 monthly, and 7 annual database restore points unless legal retention requires longer. Audit artifacts follow the stricter domain retention policy.
- Encrypt in transit and at rest. Backup operators must not reuse application credentials.

## Safe scripts

`scripts/operations/backup-database.sh --validate-only` validates local configuration without connecting. `--execute` additionally requires an exact `BACKUP_CONFIRM_DATABASE`, `MYSQL_PWD` in process environment, and an absolute target directory. It writes restrictive permissions, compressed output, and SHA-256.

`scripts/operations/restore-verify.sh` refuses every target not ending in `_restore_testing`. It verifies checksum and gzip integrity before importing, then performs a connectivity probe. Restore verification must run in an isolated account/environment with outbound messaging disabled.

Neither script was executed against production during Phase 3F-7.

Phase 3F-15 made SHA-256 tooling portable: scripts use `shasum -a 256` where available and `sha256sum` on Linux containers. The isolated exercise produced a 42,213-byte mode-0600 backup in 0.57 seconds and restored it in 3.77 seconds with matching fixture checksum and 61 migrations.

## Restore verification checklist

1. Create an empty disposable `*_restore_testing` database.
2. Disable workers, schedules, mail, webhook, Reverb, and external adapters.
3. Verify artifact checksum, restore, run migration status, integrity queries, and a read-only smoke suite.
4. Record duration and compare with RTO. Destroy the disposable database following approval.
5. Never overwrite the live database as a restore test.
