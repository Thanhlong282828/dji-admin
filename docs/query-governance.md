# Query Governance

1. Every query is tenant/company scoped before filters.
2. UI lists use `paginate()` with a bounded maximum; sequential processing uses `cursor()`, `lazyById()` or `chunkById()`.
3. List projections select required columns and required foreign keys only. Restricted fields belong to separately authorized detail queries.
4. Relations are loaded explicitly. A query-count test must show that adding rows does not add relationship queries linearly.
5. Search/sort columns use allowlists. SQL and bindings are never accepted from Operations UI input or printed by performance tooling.
6. EXPLAIN evidence records access path, candidate/chosen key and estimated rows against realistic testing data. Empty-table plans do not justify indexes.
7. Composite index column order follows equality predicates, then range/order needs. Duplicate logical indexes and generated names are normalized before recommendations.

Current Employee export gate: 10 and 60 rows remain in one eager-load window and have bounded query growth; 5,000 rows produce 10 data windows rather than per-row relationship queries.
