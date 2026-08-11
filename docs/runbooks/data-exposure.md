# Data Exposure Runbook

## Symptoms

Restricted value appears in UI, API, log, audit, export, notification or integration payload.

## Verify and contain

Record only component, classification, time and correlation reference—never the exposed value. Disable the affected delivery path using its existing governed kill switch where available; preserve evidence and revoke exposed links/credentials through their owner.

## Recover

Patch the boundary, verify redaction/authorization/company isolation, assess affected artifacts and rotate credentials only through the credential-rotation runbook. Escalate to security, data owner and legal/privacy owner. Confirm no continuing exposure before restoring delivery.
