# API Versioning Governance

Phase 3F-21 does not create `/v2`. A new version is justified only when an unavoidable breaking change cannot be delivered compatibly.

Adding endpoints, optional inputs or optional response fields is non-breaking. Removing/renaming a field or endpoint, changing type/auth/tenant semantics, making input required, removing enum values or materially changing status contracts is breaking. Every breaking proposal requires explicit approval, migration guidance, an overlap window and rollback/forward-fix assessment. Stable `operationId` values are client-facing identities.
