# Employee Core — trạng thái trước Phase 2.5B

Ngày audit: 2026-07-24. Database xác nhận `mysql / schema_admin`; bảy bảng Employee Core đã tồn tại.

| Phần | Trạng thái trước | Bằng chứng |
|---|---|---|
| Employee CRUD | FUNCTIONAL | CQRS CRUD, tenant policy và Blade đã nối |
| Assignment | SKELETON | Chỉ có Model/relation; không command, route, validation hay locking |
| Employee History | PARTIAL | Bảng/model tồn tại nhưng chưa ghi từ assignment/contract |
| Credential | SKELETON | Chỉ model và dữ liệu được eager-load |
| Employment Contract | SKELETON | Chỉ model; chưa có state machine |
| Data Consent | SKELETON | Chỉ model; chưa có immutable decision flow |
| Employee Document | SKELETON | Chỉ metadata; chưa upload/download private |
| Avatar | MISSING | Chỉ có `avatar_url` |
| User linking | PARTIAL | FK/unique tồn tại, chưa có workflow |
| Invitation | MISSING | Chưa có token table/job/notification |
| Employee export/import | MISSING | Chưa có pipeline |
| Profile UI | PARTIAL | Một trang tóm tắt |
| Scheduler | MISSING | Chưa có expiry jobs |
| PHPStan level 10 | BLOCKED | 83 lỗi thật |

Nhóm lỗi ban đầu: Eloquent/paginator/collection generics, command/query arrays, repository contracts, request/controller mixed values, event payload, nullable model results và casts.
