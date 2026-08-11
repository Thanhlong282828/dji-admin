# Enterprise Security Assurance Audit

Date: 2026-08-11. Scope: Phase 3F-16, baseline `5657bb7`. Production was audited read-only; all adversarial checks use testing data or mocks.

## Attack surface

The pre-change inventory contained 496 routes: 413 admin, 23 API, 3 health, and 57 other routes. The security dashboard adds one privileged admin GET route. Runtime classification now reports 497 total, 70 public/framework routes, 427 authenticated routes and 391 routes with an explicit permission/can/role control. Public does not mean anonymous business mutation: it includes authentication, signed/verification and health endpoints.

## Control audit

| Area | Existing control | Finding |
|---|---|---|
| Authentication | Laravel session auth, session regeneration, verified middleware, password reset contracts | No native MFA; medium residual risk for privileged users |
| Authorization | Policies/Gates, Spatie runtime permissions, IAM maker-checker, company-scoped repositories | Representative regression coverage exists |
| CSRF/session | Laravel web middleware, HttpOnly and SameSite defaults; production secure-cookie configuration | Verify production HTTPS configuration at release time |
| Integration | HMAC webhook, provider event dedupe, bounded retry, external delivery disabled by default | HTTPS-only validation did not reject private/loopback endpoints: fixed by `OutboundUrlPolicy` |
| Files | Private disks, opaque references, checksum and authorized download handlers | Malware scanning provider remains a medium design gap |
| Secrets/privacy | Secret references, redaction, data classification, protected payload sanitizer | Existing Phase 3F-13/14 controls retained |
| Supply chain/runtime | lockfiles, audit jobs, non-root image, capability reduction, no Docker socket | Read-only root filesystem remains design-dependent because Laravel cache/storage are writable |
| Logging/outbox | structured correlation, redaction, safe payload boundaries | Continue regression scanning for control characters and restricted keys |

No critical or open high finding remains. Live outbound HTTP remains disabled, so DNS rebinding/redirect enforcement is documented as a prerequisite before enabling it.
