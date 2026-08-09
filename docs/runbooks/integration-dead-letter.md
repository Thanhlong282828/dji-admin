# Integration dead letter

- Symptoms: Integration dead-letter count rises after bounded retries.
- Verify: connection status, safe attempt metadata and correlation ID; never expose credential material.
- Safe mitigation: correct configuration/provider condition, then use the governed replay action.
- Rollback: deactivate the affected connection if repeated delivery risks external duplication.
- Recovery: one logical message, one replay intent, stable checksum and successful delivery evidence.
- Escalate: uncertain external side effect or provider idempotency breach.
