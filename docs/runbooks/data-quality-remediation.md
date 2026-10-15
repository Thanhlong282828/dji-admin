# Data Quality Remediation Governance

Tài liệu này là luật thực thi lâu dài cho mọi phiên Codex thực hiện Data Quality audit, remediation, data repair, legacy accounting attestation hoặc fiscal-year mapping. Đây không phải nhật ký tiến độ.

## 1. Đọc luật trước khi thao tác

Trước mọi lệnh DQ audit hoặc repair, phải đọc:

- `AGENTS.md`
- `docs/database-ownership-governance.md`
- `docs/runbooks/production-backup-and-restore.md`
- `docs/runbooks/local-to-production-data-migration.md`
- tài liệu kiến trúc của bounded context liên quan
- `docs/architecture/LEGACY-ACCOUNTING-ATTESTATION-STANDARD.md` nếu liên quan accounting history

Không được dùng kết quả PASS hoặc checksum của phiên cũ thay cho verification hiện tại.

## 2. Database safety gate

- Mọi destructive test và rehearsal chỉ chạy trên database được phép rõ ràng.
- Trước command phải xác minh `APP_ENV`, configured database và actual PDO database.
- Ba giá trị phải khớp target đã được phê duyệt; nếu khác phải dừng fail-closed.
- `schema_admin` là production operating database và mặc định read-only.
- Không bypass `EnvironmentSafetyGuard`, không đổi connection để vượt guard.
- Không chạy repair production, migration production hoặc staging mutation nếu chưa có explicit approval đúng manifest và target.

## 3. Audit contract

- Audit command luôn read-only, deterministic và tenant-aware.
- Output phải có rule ID, entity/record ID, company/branch scope khi có, nguyên nhân cụ thể và canonical relationship IDs.
- Không xuất phone, email riêng tư, token, secret, payload chứng từ hoặc dữ liệu nhạy cảm không cần thiết.
- Output phải ghi database identity, mode, mutation count và checksum.
- Rule trùng chỉ được kết luận khi so sánh canonical record-key sets; số lượng bằng nhau không phải bằng chứng tập record bằng nhau.
- Combined rule phải tách được reason branch như missing journal, missing policy, missing mapping, wrong company, wrong source hoặc missing movement.

## 4. Repair manifest contract

- Mọi repair command mặc định là dry-run.
- Mutation bắt buộc có `--execute`, exact database confirmation, repair batch UUID, approved manifest và SHA-256 khớp chính xác.
- Manifest phải tham chiếu checksum của detailed audit mới nhất.
- Thay đổi record set, checksum, tenant scope, policy state hoặc approval làm manifest mất hiệu lực.
- Finance/Identity maker-checker phải khác nhau khi nghiệp vụ yêu cầu.
- Không tự suy đoán fiscal year, account mapping, phone value, policy hoặc business evidence.
- Rerun cùng manifest phải idempotent và không tạo duplicate economic effect.
- Mỗi execution attempt phải có immutable audit trail; failure phải rollback atomic theo tenant/batch.

## 5. Accounting immutability

- Không update/delete posted journal, journal line hoặc historical `mapping_snapshot`.
- Không retroactively ghi policy snapshot để tạo cảm giác policy đã tồn tại khi posting.
- Legacy accounting chỉ được xử lý bằng append-only attestation đã được Finance phê duyệt.
- Native policy-backed coverage và legacy-attested coverage phải được báo cáo riêng.
- Missing economic effect không được giải quyết bằng attestation; phải reconcile và dùng compensating journal qua canonical CQRS, policy resolver và open-period guard.
- Không backdate compensating entry vào closed period.

## 6. Fiscal-year mapping

- Finance phải cung cấp manifest gồm company ID, period IDs, fiscal-year name, start/end date và approval.
- Chỉ link period với fiscal year cùng company và period phải nằm trọn trong date range đã duyệt.
- Reject overlap, gap, cross-company mapping, unknown period, checksum mismatch và closed-year conflict.
- Không tự tạo fiscal calendar dựa trên phỏng đoán từ ngày của period.

## 7. Identity remediation

- Không log hoặc đưa phone value vào manifest/report.
- Invalid verified phone chỉ được xử lý bằng canonical re-verification hoặc revoke verification đã được Identity Admin duyệt.
- Không tự sửa/normalize phone và không tự đổi `phone_verified_at`.
- Mutation tương lai phải dùng optimistic locking, idempotency và immutable identity audit event.

## 8. Required tests

Trước mọi approval production phải chứng minh:

- tenant/company/branch isolation;
- authorization và maker-checker;
- dry-run không mutation;
- manifest checksum validation;
- exact retry idempotency và conflicting manifest rejection;
- process-level MySQL concurrency nếu command có mutation;
- immutable audit trail;
- accounting balance và không thay đổi posted history;
- DQ valid → corrupt → detect → restore trên testing fixture;
- focused regression và static gates phù hợp.

Không được gọi test tuần tự là concurrency proof.

## 9. Evidence placement

- Luật tái sử dụng nằm trong `AGENTS.md`, `docs/runbooks/`, `docs/architecture/` hoặc security standards.
- Output lệnh, checksum, số test, record counts, blocker và trạng thái một phiên nằm trong `docs/operations/` report riêng.
- Không ghi tiến độ tạm thời vào file luật.
- Không commit production-derived manifest chứa dữ liệu nhạy cảm; artifact phải được lưu ở vị trí bảo mật được phê duyệt.

## 10. Stop conditions

Dừng ngay khi database identity sai, manifest/checksum không khớp, thiếu approval, tenant scope không rõ, posted history có nguy cơ bị rewrite, hoặc rollback/compensation chưa được xác định.
