# Idempotency and Lock Governance

| Context | Logical identity / guard | Lock order principle |
|---|---|---|
| Workflow | outbox/event UUID, task/instance transition | definition/version → instance → task/outbox |
| Notification | message scope, recipient/channel delivery identity | message → recipient → channel attempt/outbox |
| IAM | request/event identity, consumer checkpoint uniqueness | request → review/approval → assignment → Spatie/outbox |
| Integration | connection + idempotency UUID, provider event receipt | connection → message → attempt/dead-letter/reference |
| Audit | source module + source event + consumer checkpoint | checkpoint/source → event → snapshot/export/archive |
| Payroll/ERP/Government | run/export/filing identities and immutable checksum | period/run → item/artifact → approval/outbox |

`exists()` checks alone are insufficient for external/event identity; database unique constraints or locked rows remain authoritative. Retry is bounded. Corrections append history rather than rewriting immutable evidence. External HTTP/mail must not run while holding avoidable long business locks. Existing proven lock orders are changed only with process-race evidence.
