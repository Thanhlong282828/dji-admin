# Payroll Run lifecycle

Implemented transitions: draft → calculating → calculated/validation_failed/failed; calculated → submitted; validation_failed/failed/rejected → calculating; submitted → approved/rejected; approved → finalized. Unsafe direct transitions and any mutation after finalized are rejected.

One Run is allowed per company/Payroll Period. Run references the exact Payroll Input Snapshot ID. Every mutation uses optimistic version and UUID history idempotency. Approval rejects creator/submitter self-approval. Finalization means immutable payroll result only; it does not mean paid and creates no bank file or payslip.

Stable transaction intent is Period/Input Snapshot → Run → assignments → items/components → history, with three bounded attempts. Business/version conflicts are not converted into success.
