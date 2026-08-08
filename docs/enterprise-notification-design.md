# Enterprise Notification Center Design

Notification Center owns versioned templates, immutable messages, resolved recipients, per-channel delivery intents, append-only attempts, preferences, read state and its notification request outbox. Source bounded contexts retain their state and authorization; messages contain only safe references and users fetch sensitive details from the owning authorized endpoint.

## Aggregate and delivery

`QueueNotificationData` is immutable and normalized at the boundary. `NotificationPayloadGuard` rejects salary, tax, bank, identity, private path/token and private HR fields before persistence. A message pins the exact published template version and SHA-256 payload checksum. Company+idempotency key creates one message; message+recipient and the existing durable channel uniqueness constraints create one delivery intent.

Flow: domain outbox → `QueueNotificationCommand`/Workflow outbox consumer → `NotificationDeliveryService` transaction → recipient/channel intents → sender → append-only attempt → delivered/partial. Database, broadcast and email have separate senders. A failed channel is retried under a delivery row lock with a maximum of three attempts; a successful channel is never repeated.

## Recipients and privacy

Resolvers cover specific user, permission, manager, Workflow assignee and domain actor. Every result intersects `company_user_access`; duplicates are removed and actor exclusion is configurable. Preferences are per company/user/category. Security-category database and broadcast delivery cannot be disabled. Email is opt-in by default.

## Concurrency

Canonical lock order is message/idempotency → delivery → attempt, and recipient/message → read state. MySQL process races cover queue/queue, delivery/delivery, retry/retry, read/read and mark-all/read. Unique constraints plus row locks guarantee one message, one recipient/channel intent, deterministic attempt count and one read state.
