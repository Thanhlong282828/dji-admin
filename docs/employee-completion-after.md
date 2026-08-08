# Employee Core — trạng thái sau Phase 2.5B

| Phần | Trạng thái | Ghi chú |
|---|---|---|
| Employee CRUD | COMPLETED | CQRS, tenant policy, validation, encrypted identity |
| Assignment | COMPLETED | Create/update/end/transfer/promotion, locks, allocation/primary/overlap, history, realtime |
| Employee History | COMPLETED | Assignment và contract transition ghi timeline |
| Credential metadata | COMPLETED | Typed create/update/archive/restore, encrypted number |
| Credential private evidence | PARTIAL | Private document nền tảng có; dedicated replace/download UI chưa nối |
| Contract state machine | COMPLETED | Submit/approve/return/activate/cancel/terminate, invalid transition và overlap bị chặn |
| Signed contract/renewal | PARTIAL | Private document nền tảng có; action chuyên biệt chưa nối |
| Data Consent | FUNCTIONAL | Grant/withdraw immutable, actor/reason/expiry |
| Consent evidence/renew | PARTIAL | Schema có; route chuyên biệt chưa nối |
| Employee Document | COMPLETED | Private disk, MIME/size, UUID, checksum, version lock, scan gate, authorized download |
| Malware scan | FUNCTIONAL | Local/test skipped; production mặc định ClamAV fail-closed, runtime ClamAV chưa xác minh |
| Avatar | FUNCTIONAL | MIME/size/dimension, atomic replace/remove; thumbnail riêng chưa có |
| User link/unlink | COMPLETED | Tenant, one-to-one, transaction/lock, unlink không xóa User |
| Invitation | FUNCTIONAL | Token random, chỉ lưu hash, expiry 48h, invalidation, notification đồng bộ để token không bị serialize plaintext vào queue |
| Create User/disable access | NOT_STARTED | Không tạo route giả |
| Profile UI | FUNCTIONAL | Permission-aware sections, dữ liệu thật |
| CSV export | COMPLETED | Streaming, BOM, filter, tenant, formula sanitizer, không xuất sensitive |
| Import | BLOCKED | Chưa có preview/idempotency/failed-row pipeline đủ an toàn; không tạo UI giả |
| Scheduler | COMPLETED | Contract expiry và expiry detection jobs |
| PHPStan Employee | COMPLETED | Level 10, 0 lỗi |

Employee Core chưa được đánh dấu hoàn tất toàn bộ vì các mục `PARTIAL/BLOCKED/NOT_STARTED` vẫn còn.
# Recruitment source integration

**COMPLETED:** Employee supports unique source candidate/application/offer links. Hire creates a preboarding Employee and primary assignment while reusing Employee validation services. User credentials are not auto-created; invitation remains explicit.
