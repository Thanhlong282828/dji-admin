# Interview and Offer workflow audit

Audit date: 2026-07-24. Database: MySQL `schema_admin`.

| Capability | Schema/model/enum | Service/CQRS | Policy/permission | Route/UI | Event/job/test | Status |
|---|---|---|---|---|---|---|
| Interview persistence | Present | Missing | Permissions present | Missing | Missing | SKELETON |
| Interview scheduling/transitions | Present | Missing | Missing policies | Missing | Missing | MISSING |
| Interviewer assignment | Present | Missing | Missing policies | Missing | Missing | MISSING |
| Evaluation | Present | Missing | Missing policies | Missing | Missing | MISSING |
| Offer persistence | Present | State-machine only | Permissions present | Missing | Missing | SKELETON |
| Offer approval/send/decision | Present | Missing | Missing policies | Missing | Missing | MISSING |
| Offer vacancy reservation | No marker | Validator only | Override permission | Missing | Missing | MISSING |
| Offer document | Metadata columns | Missing | Download permission | Missing | Missing | SKELETON |

No Interview or Offer rows existed at audit time. Existing requisition, candidate,
application and onboarding records were left unchanged. All four active HR modules
passed PHPStan level 10 before this implementation.

