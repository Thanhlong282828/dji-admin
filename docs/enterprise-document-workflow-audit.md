# Enterprise Document / Workflow Audit

## Ownership decision

`Modules/Document` is the owner of company-scoped document metadata and immutable private file versions. Employee-specific documents remain owned by Employee; Product/Article images remain owned by the shared image-media boundary. Cloudflare Images is not used for PDF/DOCX evidence.

| Capability | Owner | Runtime | Status |
|---|---|---|---|
| Employee private document | Employee | `employee_documents`, private download, checksum | COMPLETE |
| Enterprise document metadata/version | Document | `enterprise_documents`, `enterprise_document_versions` | COMPLETE |
| Private file storage | Document/shared filesystem | Laravel local private disk | COMPLETE |
| SHA-256/version history | Document | immutable numbered versions | COMPLETE |
| Approval orchestration | Workflow | definitions, instances, tasks | COMPLETE |
| Approval Inbox | existing cross-project surface | owner deep-links | COMPLETE |
| Notification Center | existing notification/outbox runtime | database delivery | COMPLETE |
| Audit trail | existing Activity Log | metadata only, never file contents | COMPLETE |
| External private object storage | infrastructure | no verified private provider | CONFIGURATION_REQUIRED |

## Boundary

Document commands may create metadata, upload a version, or archive metadata. They never update Employee, Procurement, Finance, Commerce, or Workflow business tables. Workflow remains orchestration evidence; the owner module command performs the approved business mutation.

## Security and retention semantics

Every operation resolves explicit Company access and permission middleware. Cross-company subjects are rejected. Downloads stream through an authorized route and do not expose storage paths. Archive is metadata-only and does not delete historical versions. `retention_until` is configured metadata, not a claim of legal compliance.
