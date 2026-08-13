# Archive and Disposal Governance

Backup is a recovery copy; archive is governed long-term lifecycle evidence. Archive metadata requires SHA-256, byte size, content type, creation time, policy key and source range. Private object paths are not dashboard/API evidence.

Disposal is never a blind delete. Eligibility requires elapsed approved retention, no hold, no active business/FK dependency, owning-domain adapter availability, authorized scope and dry-run evidence. Audit/workflow/checkpoint integrity may require archive or permanent retention.

The current command and dashboard are read-only. Disposal defaults disabled and no production or development record is deleted.
