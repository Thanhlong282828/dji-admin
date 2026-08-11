# Enterprise Data Protection Design

## Evaluation flow

`DataFieldRegistry → DataProtectionPolicyService(field, purpose, actor context, surface) → allow|mask|redact|deny`.

The registry is intentionally small and focused on sensitive categories. Field definitions include domain, owner, classification, display/log/export policy, masking rule, encryption expectation and retention expectation. Business policy such as Payroll calculation and employment authorization is not represented as privacy configuration.

## Safe defaults

- Restricted UI/API values: deny unless authorization and purpose are both explicit.
- Confidential UI/API values: mask without explicit authorization and purpose.
- Audit/log: redact regardless of caller permission.
- Integration/notification: deny without explicit authorization and purpose.
- Export: deny by default; domain export permission and purpose remain mandatory.
- Cache failures must not expose values; security/side-effect protections fail closed.

Audit redaction produces `{changed: true, classification: restricted, value: [REDACTED]}` and checksums the safe form. This preserves change evidence without creating a PII replica.

## Data lifecycle

Consent is evidence of purpose, not a replacement authorization system. Withdrawal history remains immutable. Retention resolution uses classification, owner, legal basis, archive rule and deletion eligibility. Hard delete, archive, anonymization and pseudonymization are distinct decisions. No automated deletion is implemented.
