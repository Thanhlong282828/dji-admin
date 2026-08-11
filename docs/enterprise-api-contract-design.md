# Enterprise API Contract Design

`App\Support\ApiContract` owns cross-cutting read models: `ApiContractAuditService`, `BreakingChangeClassifier`, `SafeExampleScanner` and typed `ApiContractFinding`. It reads route metadata and generated OpenAPI only; it never invokes controllers or mutates domain state.

Coverage, orphan, duplicate-ID, security, validation, response, tenant, pagination and privacy findings fail independently of the baseline. Normalization sorts object keys before SHA-256 calculation. The dashboard reads a bounded snapshot; contract reflection/parsing is not middleware on production requests.

Route ownership remains in existing modules. Current APIs remain stable; a version boundary is introduced only when a required breaking change cannot be delivered compatibly.
