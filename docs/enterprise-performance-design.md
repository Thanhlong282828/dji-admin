# Enterprise Performance Design

Performance engineering is an operational read-side concern. It may measure query count, bounded memory, export iteration, cache scope, index contracts and capacity, but it does not own or change HR, Payroll, Workflow, IAM, Integration, Notification or Audit semantics.

## Contracts

- Lists use bounded pagination and explicit columns. Detail projections remain separate.
- Sequential exports use cursor/chunk/lazy iteration, explicit order and formula-safe streaming.
- N+1 gates compare query growth as row count grows; CI does not enforce laptop millisecond thresholds.
- Cache keys contain technical company/version/filter hashes, never PII. Cached values are typed arrays or serialization-safe DTOs.
- Index findings require a realistic testing dataset plus logical WHERE/JOIN/ORDER evidence. `type != const` alone is not a migration reason.
- Performance findings are typed read-only values with QUERY, INDEX, CACHE, MEMORY, EXPORT, QUEUE or HTTP categories.
- `/admin/operations/performance` and `operations:performance-audit` never accept SQL or reveal bindings.

Employee export uses `lazyById(500)`: every window eager-loads only manager, primary assignment/unit/position, latest contract and expiring credential data. This bounds memory and relationship queries while preserving export row ordering/content.
