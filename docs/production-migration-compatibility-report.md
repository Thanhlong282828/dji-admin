# Production migration compatibility report

Inventory: 64 migration files. Phase 3F-30 created no migration; Commerce C1/C2 later adds one additive migration for Branch, profiles, Store and shared media ownership.

The inventory is predominantly additive tables, columns, indexes and foreign keys. Several `down()` paths drop columns/tables, and compatibility migrations include guarded column replacement/drop operations. Those paths make blanket production rollback unsafe. Each future release must classify locking risk, backfill volume, index creation and code compatibility using expand → deploy compatible code → bounded backfill → contract in a later release. Readiness and smoke commands never execute migrations.
