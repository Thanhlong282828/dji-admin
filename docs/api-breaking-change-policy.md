# API Breaking Change Policy

The contract gate classifies operation removal, stable operationId/auth change and newly required input as `BREAKING`; removed documented responses are `POTENTIALLY_BREAKING`; new operations are `ADDITIVE`.

Raw JSON diff is not a semantic verdict. Formatting/description updates are documentation-only; field types, enums, nullable/required state, tenant and security need review. Approved breaking changes require owner, approval reference, replacement/version and consumer migration plan in release evidence.
