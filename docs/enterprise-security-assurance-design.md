# Enterprise Security Assurance Design

Security assurance is a read-only operations capability. It owns typed findings, defensive policy validation, threat/control evidence and presentation; it does not own authentication, IAM state, business authorization, incident state or production security mutation.

`OutboundUrlPolicy` is the explicit SSRF boundary. It requires HTTPS, forbids embedded credentials, localhost/local names, and private/reserved IP literals. `GenericHttpAdapter` validates through this policy before evaluating mock delivery. External delivery remains disabled. Before live delivery is enabled, add DNS resolution pinning, validate every resolved address, disable redirects (or revalidate every hop), bound response bytes and retain TLS verification.

`SafePathPolicy` provides a reusable filename boundary that rejects path separators, decoded traversal and NUL bytes. Aggregate download handlers continue to own tenant authorization, integrity and private-storage access.

`SecurityAssuranceService` composes route/control evidence into typed, secret-free data. `operations:security-assurance` and `/admin/operations/security-assurance` are read-only. The web surface requires `auth`, `verified` and `operations.view`.

Residual risks are explicit: privileged MFA and malware scanning are not falsely represented as implemented. No security finding table or migration is required.
