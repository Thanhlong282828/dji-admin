# DQ Phase 2A Security Hardening Evidence — 2026-10-16

This file records execution evidence only. Permanent rules live in `docs/runbooks/data-quality-remediation.md`.

## Scope

- Baseline branch: `codex/erp-finance-tax-runtime-hardening`
- Baseline release SHA: `43f297f9facea706ccb77d16f7bf4724c33a3356`
- Execution scope: local source and `schema_admin_testing` only
- Staging, production, Supabase, NAT, restore, migration, deploy, stage, commit and push: not performed

## Closed security findings

1. GCE runtime identity now enumerates every NIC and access config; any external IP makes authorization fail closed.
2. Metadata requests disable redirects, require `Metadata-Flavor: Google` on the request and response, and fail closed on missing, spoofed, redirected, timed-out or malformed metadata.
3. CLI/environment-provided approval checksum, release SHA, backup SHA and production UUID are no longer trusted. Authorization uses fixed-path, root-owned, non-writable approval, immutable release identity and verified restore receipts provisioned through a separate privileged channel.
4. Testing payload does not contain staging-only authorization evidence, preserving deterministic testing checksum compatibility when the record set is unchanged.
5. Trust paths are checked lexically: parent and final-file symlinks are rejected, every parent must be a root-owned non-writable directory, the final object must be a root-owned non-writable regular file, and `lstat` path identity must match the opened handle by device/inode before and after reading.

## Trust evidence contract

- Approval receipt: `/etc/laraveldji/dq-staging-approval.json`
- Immutable release identity: `/opt/laraveldji/release-identity.json`
- Verified restore receipt: `/etc/laraveldji/dq-staging-restore-receipt.json`
- Files and parent directories: root-owned, not group/other writable, not symlinks
- Private signing key: not used or stored by the application
- Live root-owned receipt provisioning and GCE identity: not verified in this local phase
- TOCTOU residual risk: PHP does not expose a portable `openat2(RESOLVE_NO_SYMLINKS)`/`O_NOFOLLOW` contract here. Lexical `lstat`, root-owned non-writable parents and repeated path/handle device-inode comparisons fail closed for detectable replacement, but this is not claimed as an absolute OS-level race-proof guarantee.
- `.env.example` provenance: `DQ_STAGING_DATABASE` belongs to Phase 2A. `RELEASE_REVISION` is an earlier Batch/release change retained in the working tree and must not be included by whole-file staging in a Phase 2A-only commit.

## Verification results

- Database safety preflight: `APP_ENV=testing`, configured database `schema_admin_testing`, actual PDO database `schema_admin_testing`
- Unit hardening suite: 32 passed, 70 assertions
- DQ remediation feature suite, including MySQL process race: 6 passed, 23 assertions
- DQ regression suite: 5 passed, 114 assertions
- Total focused evidence: 43 passed, 207 assertions, 0 failed
- PHPStan: PASS, 0 errors
- Pint: PASS
- PHP syntax: PASS
- `git diff --check`: PASS
- Project tracked-file secret scan: PASS
- Untracked-file secret-pattern scan: PASS; no matching file paths

## Remaining staging verification

- Provision all three trust receipts through the privileged staging channel and verify owner/mode in situ.
- Verify the release identity is embedded in the immutable image matching the approved release SHA.
- Verify the restore receipt is produced only after independent backup checksum and restore verification.
- Exercise real GCE metadata, multiple-NIC enumeration, server UUID and `SHOW GRANTS` against the isolated staging clone.
- Run the detailed audit in `DRY_RUN_READ_ONLY` mode and retain its redacted checksum evidence.
