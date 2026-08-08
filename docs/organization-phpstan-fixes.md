# Organization PHPStan level 10 fixes

Date: 2026-07-24

The complete baseline is stored at `storage/logs/phpstan-organization-before.txt`.

- Initial result: **132 errors**.
- Application layer baseline: 21 errors.
- Infrastructure layer baseline: 62 errors.
- Presentation, contracts, events and cross-layer inference formed the remainder.

| Group | Resolution |
|---|---|
| Eloquent generics | Kept exact `BelongsTo`/`HasMany` related and declaring model types. |
| Recursive tree shape | Added explicit row/node shapes compatible with the installed PHPStan. |
| Paginator generics | Declared paginator key and item types in repositories and handlers. |
| Command/query typing | Added explicit result types aligned with repository contracts. |
| Repository contracts | Added typed filter/data maps and exact results. |
| Repository implementation | Normalized DB boundaries and typed closures, joins, callbacks and transforms. |
| Controllers | Normalized request arrays and authenticated identifiers before CQRS dispatch. |
| Form Requests | Typed validation rules and replaced unsafe casts with `integer()`. |
| Broadcast | Typed channels and minimal payload shape. |
| Legacy skeleton | Replaced unused generated controller methods with a deprecated compatibility class. |
| Export | Typed DTO, query, handler, generator rows and streamed response. |

Progress: **132 → 58 → 20 → 0**. Export was added afterwards and the result remained zero.

Final result:

```text
./vendor/bin/phpstan analyse Modules/Organization/app --level=10 --memory-limit=1G
[OK] No errors
```

No baseline, `ignoreErrors`, suppression comment or lower level was introduced.
