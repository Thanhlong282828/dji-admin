# Webhook Compromise

Symptoms: signature failures, replay spike or unknown provider events. Verify connection, event identifiers, checksums and receipt status. Disable the specific connector/kill switch, rotate the referenced secret through the secret boundary, retain receipts and dead letters, then replay only verified events. Confirm HMAC validation, dedupe and downstream state before recovery; escalate to the integration and security owners.
