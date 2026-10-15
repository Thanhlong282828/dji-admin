# Quy ước phát triển toàn project

File này áp dụng cho toàn bộ repository. Mọi thay đổi mới hoặc chỉnh sửa chức năng hiện có phải tuân thủ các nguyên tắc dưới đây, trừ khi người dùng đưa ra yêu cầu cụ thể khác.

## LUẬT SỬ DỤNG FILE MARKDOWN CHO CODEX

- Các file `.md` thuộc governance, architecture, security standard và runbook là **luật thực thi lâu dài** cho Codex, không phải nơi ghi nhật ký tiến độ hoặc mô tả “đang làm gì”.
- Trước mỗi yêu cầu/lệnh mới, Codex phải đọc lại `AGENTS.md` và các file `.md` bắt buộc được dẫn chiếu phù hợp với phạm vi công việc rồi mới thao tác.
- Khi cập nhật file luật, chỉ ghi quy tắc có thể tái sử dụng cho các phiên sau: điều kiện an toàn, kiến trúc, thứ tự kiểm tra, hành động được phép/cấm và tiêu chí hoàn thành.
- Không ghi vào file luật các status tạm thời, lời tường thuật phiên hiện tại, số test của một lần chạy, danh sách việc vừa làm hoặc câu kiểu “hiện đang thực hiện”.
- Kết quả audit/test lịch sử nếu cần lưu phải nằm trong tài liệu evidence/report được đặt tên rõ ràng; Codex chỉ dùng làm bằng chứng tham khảo và phải kiểm chứng lại trạng thái thực tế trước khi hành động.
- Không được lấy một kết quả PASS cũ trong Markdown để tuyên bố task mới đã PASS. Mỗi phiên phải chạy lại gate cần thiết theo đúng database/environment an toàn.
- Khi yêu cầu mới mâu thuẫn với luật hiện có, Codex phải nêu rõ xung đột và chỉ thay đổi luật khi người dùng cho phép rõ ràng; không âm thầm bỏ qua luật.

## Kiến trúc bắt buộc: Module + CQRS

- Mỗi nghiệp vụ phải nằm đúng module/bounded context sở hữu dữ liệu và hành vi đó; không đặt business logic rải rác trong Blade, controller hoặc module khác.
- Tách rõ luồng đọc và ghi theo CQRS:
  - Đọc dữ liệu qua Query/Query Handler/read model hoặc lớp truy vấn tương đương của module.
  - Ghi dữ liệu qua Command/Command Handler/application service và domain rules của module.
- Controller và Blade chỉ điều phối, render view model và gửi command/query; không truy vấn hoặc cập nhật dữ liệu nghiệp vụ trực tiếp.
- Luôn giữ đúng tenant/company/branch scope, quyền truy cập, ownership và các điều kiện nghiệp vụ hiện có.
- Không ghép dữ liệu của các bounded context khác nhau chỉ vì chúng trông giống nhau trên giao diện.

## Truy xuất nguồn dữ liệu và liên kết điều hướng

- Mọi KPI, con số tổng hợp, mã tham chiếu hoặc bản ghi hiển thị phải cho người dùng biết dữ liệu lấy từ đâu.
- Hàng số liệu đang nằm rời bên ngoài bố cục phải được chuyển thành KPI card phù hợp tone giao diện của project.
- Mỗi card phải có:
  - Nhãn rõ nghĩa.
  - Giá trị đã định dạng đúng.
  - Ngữ cảnh hoặc mô tả nguồn tính toán khi cần.
  - Link dẫn tới workspace, danh sách, chứng từ hoặc màn hình chi tiết canonical đã tạo ra số liệu đó.
- Trong table, các giá trị nhận diện được như reference, document number, employee, vendor, customer, order, invoice, journal, payroll run hoặc nguồn tổng hợp phải có link tới bản ghi/màn hình nguồn tương ứng khi người dùng có quyền truy cập.
- Link phải giữ nguyên `company_id`, branch scope và các filter cần thiết để người dùng không mất ngữ cảnh.
- Nếu việc xác định URL phụ thuộc loại nguồn hoặc nghiệp vụ, resolve URL trong query/read model/view model dùng chung; không lặp mapping phức tạp trong từng Blade.
- Link phải kiểm tra route, quyền và sự tồn tại của bản ghi. Khi không có quyền hoặc không thể resolve nguồn, vẫn hiển thị giá trị dạng text và trạng thái giải thích phù hợp, không tạo link hỏng.
- Nhãn hành động phải nói rõ đích đến, ví dụ `Mở đơn hàng nguồn →`, `Xem journal →`, `Mở workspace tồn kho →`; không dùng hành động mơ hồ khiến người dùng không biết sẽ đi đâu.

## Dữ liệu thật và luồng nghiệp vụ thật

- Không tạo demo row, synthetic row, clone, hard-code hoặc dữ liệu lấp chỗ chỉ để bảng trông có dữ liệu.
- Bảng chỉ được có dữ liệu từ luồng nghiệp vụ thật, bảng canonical thật và read model đúng nguồn.
- Khi bảng trống, trước tiên phải kiểm tra luồng upstream nào có trách nhiệm tạo dữ liệu và nối đúng luồng đó vào query hiện tại.
- Nếu thực tế chưa có bản ghi, hiển thị empty state trung thực gồm:
  - Lý do chưa có dữ liệu.
  - Luồng/module sở hữu nguồn dữ liệu.
  - CTA dẫn tới bước nghiệp vụ hợp lệ để tạo hoặc xử lý dữ liệu, nếu người dùng có quyền.
- Không coi `0` hoặc bảng trống là lỗi nếu đó là kết quả nghiệp vụ hợp lệ.
- Không seed, migration dữ liệu vận hành, sửa trực tiếp database hoặc tự tạo transaction để làm đầy màn hình nếu người dùng chưa yêu cầu rõ ràng.
- Khi nối dữ liệu giữa các module, phải giữ lineage/reference thật và trạng thái nghiệp vụ thật; không sao chép dữ liệu để tạo cảm giác đã tích hợp.

## UI/UX theo tone project

- Giữ ngôn ngữ thiết kế tối–vàng hiện có, typography, spacing, border, trạng thái và interaction nhất quán với các màn hình tốt trong project.
- Ưu tiên tái sử dụng component hiện có cho KPI card, source link, empty state, panel, table, status pill và action thay vì tạo markup/CSS riêng lẻ.
- Các con số tổng hợp dùng card/grid responsive; không để thành các dòng text rời, dính nhau hoặc nằm ngoài container.
- Table phải dễ quét, có hierarchy rõ, số tiền/cột số căn chỉnh hợp lý, trạng thái dễ nhận biết và link nguồn có affordance rõ ràng.
- Với danh sách dài, dùng layout card, ranked list hoặc table phù hợp; tránh trải text thô trên toàn chiều ngang màn hình.
- Bảo đảm hover, focus, keyboard navigation, độ tương phản, wrapping và responsive trên các kích thước màn hình phổ biến.
- Không đưa giao diện trắng/raw browser style hoặc component lệch tone vào project.

## Tiêu chí hoàn thành

- Kiểm tra các màn hình tương tự trong phạm vi thay đổi và áp dụng nhất quán quy ước card + source link + empty state.
- Xác minh route name, middleware, permission và ID từ read model trước khi sinh link.
- Xác minh dữ liệu hiển thị truy ngược được tới nguồn canonical và đúng company/branch.
- Chạy kiểm tra phù hợp với thay đổi: compile Blade, focused tests, accessibility/UI checks, Vite build khi sửa CSS/JS và `git diff --check`.
- Không hoàn thành công việc nếu còn link nguồn bị hỏng, dữ liệu giả để lấp bảng, business logic sai module hoặc vi phạm CQRS.

## Nguyên tắc sản phẩm đã chốt

> Hàng view số nằm ngoài phải thành card và gắn link tới nơi lấy dữ liệu. Thông tin trong table phải có link tới nguồn của nó. Phát triển theo Module + CQRS, giữ UI/UX đúng tone project. Bảng chưa có dữ liệu phải được nối vào luồng nghiệp vụ thực tế, không tạo dữ liệu cho có. Mọi thứ làm ra phải chỉ rõ và điều hướng được về nguồn.

## MANDATORY ERP NAVIGATION GOVERNANCE

Trước khi sửa bất kỳ module user-facing hoặc navigation nào, phải đọc đầy đủ:

- `docs/architecture/ERP-SIDEBAR-STANDARD.md`
- `docs/architecture/ERP-NAVIGATION-MAP.md`

Every future user-facing LaravelDji ERP capability MUST be classified into the canonical ERP navigation domain defined in docs/architecture/ERP-SIDEBAR-STANDARD.md.

Before implementation, identify its owning module, primary navigation group, route, permission, tenant scope and Process Help requirements.

After implementation, register the workspace in the canonical sidebar, update breadcrumbs and active navigation states, update ERP-NAVIGATION-MAP.md, and run navigation regression tests.

Do not create duplicate workspaces or empty navigation groups.

Backend-only services, jobs, events, APIs and technical capabilities do not require sidebar entries unless they expose a genuine user-facing workspace.

This standard applies to all future development phases unless explicitly superseded by an approved architecture decision.

Mỗi feature user-facing phải hoàn tất checklist trong `ERP-SIDEBAR-STANDARD.md`: module owner, domain, functional area, route, permission, company/branch scope, một sidebar entry canonical, active state, breadcrumb, Process Help, source traceability, navigation map, tests desktop/mobile, authorization và tenant isolation. Không được coi UI là hoàn thành nếu còn mục áp dụng chưa đạt.

## MANDATORY ENVIRONMENT CONFIGURATION GOVERNANCE

Trước khi thêm, đổi tên, xoá hoặc sử dụng bất kỳ biến `.env` nào, phải đọc đầy đủ:

- `docs/environment-configuration-governance.md`

Mọi biến môi trường phải được khai báo trong `.env.example` không kèm secret thật, được ánh xạ qua `config/*`, và application/module code chỉ đọc bằng `config()`. Không được dùng `env()` ngoài file cấu hình, không đưa credential server-side vào `VITE_*`, không ghi secret/token vào log, tài liệu, test fixture hoặc commit. Các provider bên ngoài phải fail closed, có timeout, quyền tối thiểu và kiểm chứng database/environment trước mọi thao tác có side effect.

## MANDATORY VPS & PRODUCTION DEPLOYMENT GOVERNANCE

Trước mọi yêu cầu liên quan VPS, production host, SSH/IAP, triển khai, rollback, service runtime, production migration, queue worker, scheduler, ClamAV, monitoring hoặc alert delivery, Codex phải đọc đầy đủ:

- `docs/runbooks/production-deployment.md`
- `docs/enterprise-release-governance.md`
- `docs/database-ownership-governance.md`
- `docs/environment-configuration-governance.md`
- `docs/operations/PRODUCTION-MONITORING-ARCHITECTURE.md`

`docs/runbooks/production-deployment.md` là luật vận hành lâu dài. Mọi quy tắc VPS mới có tính tái sử dụng phải được cập nhật vào runbook này; trạng thái tạm thời, output lệnh, blocker của một phiên và số liệu runtime phải lưu trong report riêng dưới `docs/operations/`, không ghi thành tiến độ trong `AGENTS.md` hoặc runbook.

Read-only audit không cho phép deploy, restart, cài package, migrate, sửa `.env`, IAM, firewall, SSH key, metadata, database, queue, object storage hoặc gửi alert. Mỗi mutation cần explicit approval riêng và phải qua identity gate, backup/restore verification, staging validation, rollback plan cùng post-deployment checks. Không dùng bằng chứng runtime của artifact cũ để tuyên bố source/release mới đã production-ready.

## MANDATORY BACKUP & RESTORE GOVERNANCE

Trước mọi thao tác backup, restore, release, production migration, rollback, database recovery hoặc persistent-storage recovery, Codex phải đọc đầy đủ:

- `docs/runbooks/production-backup-and-restore.md`

Read-only inventory không cho phép tạo dump, snapshot, object copy hoặc restore. Mỗi mutation production cần explicit approval đúng target. Backup production phải mã hóa, lưu off-host, có checksum và restore chính xác vào môi trường cô lập; command exit 0 không phải bằng chứng recoverability. Không bao giờ restore đè database/directory đang vận hành. Metadata Supabase chỉ được báo `INVENTORY ONLY` nếu chưa có byte-level backup được phê duyệt và kiểm chứng. Kết quả từng lần chạy nằm trong report `docs/operations/`, còn runbook chỉ chứa luật tái sử dụng.

## MANDATORY LOCAL-TO-PRODUCTION DATA MIGRATION GOVERNANCE

Trước mọi yêu cầu so sánh, chuẩn bị hoặc chuyển dữ liệu local/staging sang production, Codex phải đọc đầy đủ:

- `docs/runbooks/local-to-production-data-migration.md`

Production data luôn là nguồn authoritative; tuyệt đối không import dump local đè production. Chỉ dùng allowlist, tenant mapping, ID remapping, dry-run mặc định, idempotency, audit/reconciliation và staging cô lập đã restore từ production backup. Không copy password, token, session, credential hoặc test/demo data. Database metadata và private object bytes là hai migration unit riêng; Supabase object transfer cần approval riêng. Mọi production import cần backup restore-verified và explicit approval đúng manifest.

## MANDATORY DATA QUALITY REMEDIATION GOVERNANCE

Trước mọi yêu cầu liên quan Data Quality audit, data repair, remediation manifest, legacy accounting attestation, fiscal-year mapping hoặc sửa identity verification, Codex phải đọc đầy đủ:

- `docs/runbooks/data-quality-remediation.md`

Audit phải read-only, deterministic, tenant-aware và xác minh configured/actual database. Mọi repair mặc định dry-run; mutation cần exact database confirmation, approved manifest SHA-256, batch UUID, authorization/maker-checker và audit trail. Không tự suy đoán fiscal year, phone, policy hoặc account mapping. Không update/delete posted journal, journal line hoặc historical mapping snapshot. Kết quả chạy và số liệu phiên nằm trong `docs/operations/`; runbook chỉ chứa luật tái sử dụng.

## MANDATORY SUPABASE STORAGE & RLS SECURITY GOVERNANCE

Trước khi sửa Supabase Storage, S3 adapter, private document flow, signed URL, PostgreSQL/Data API, RLS policy, grant hoặc Supabase credential, phải đọc đầy đủ:

- `docs/security/SUPABASE-STORAGE-RLS-SECURITY-STANDARD.md`

Supabase S3 access keys là privileged server credentials và không bị Storage RLS giới hạn như user JWT. Laravel route permission, Policy/Gate và tenant scope luôn là security boundary bắt buộc. Không tạo public bucket/policy cho ERP document, không đưa key vào browser, không thêm Supabase Auth như hệ đăng nhập thứ hai, và không thay đổi cloud production policy/DDL nếu chưa có audit read-only, development proof, rollback plan và explicit approval.

### SUPABASE S3 SECURITY VERIFICATION GOVERNANCE

Khi làm việc với Supabase, Storage, Enterprise Documents, Employee Documents hoặc tenant authorization, phải đọc đầy đủ:

- `docs/security/SUPABASE-S3-SECURITY-VERIFICATION.md`
- `docs/security/SUPABASE-S3-LIVE-VERIFICATION-RUNBOOK.md` khi audit live connectivity, PostgreSQL/Storage RLS hoặc chuẩn bị bất kỳ object lifecycle verification nào.
- `docs/security/SUPABASE-S3-PHASE3-FINAL-AUDIT.md` cho checkpoint Phase 3, ma trận PASS/BLOCKED và ranh giới bằng chứng hiệu lực.

Trước khi sửa, phải xác minh database/environment và chạy các kiểm tra read-only cùng test fake/mock an toàn phù hợp. Sau khi sửa phải chạy lại regression liên quan và cập nhật tài liệu nếu endpoint, storage adapter, permission, object path hoặc test command thay đổi. Không tự chạy live production upload/download/delete, migrate, thay đổi RLS/bucket/policy hay test phá hủy dữ liệu. Không dùng kết quả cũ để đánh dấu PASS; nếu test database không an toàn hoặc thiếu quyền/credential thì phải ghi `BLOCKED` cùng lý do.

### LUẬT VẬN HÀNH SUPABASE S3 BẮT BUỘC

- Mỗi phiên Codex có thao tác liên quan Supabase/Document Storage phải đọc `AGENTS.md`, security standard, RCA, verification document và live runbook trước khi chạy lệnh.
- Các file Markdown governance/runbook là luật để quyết định hành động cho phiên mới; không dùng chúng làm nhật ký “đang làm gì”. Kết quả kiểm thử lịch sử chỉ là bằng chứng tham khảo và không thay thế verification của phiên hiện tại.
- Disk `supabase` phải tiếp tục dùng driver ACL-neutral `supabase-s3`; không sửa `vendor/`, không gửi `ACL`/`Grant*`, không làm thay đổi disk S3/R2/Images/local khác.
- Bucket ERP phải private. Server S3 credentials không được đưa vào `VITE_*`, browser, HTML, JavaScript, log, test output hoặc tài liệu.
- Laravel authentication, permission/policy và tenant/company scope luôn là security boundary bắt buộc; không dựa vào Storage RLS để thay thế vì server S3 credentials là privileged path.
- Chỉ chấp nhận MIME nằm trong bucket allowlist đã được xác minh: `application/pdf`, `image/jpeg`, `image/png`, `image/webp`. `ContentType` phải lấy từ nội dung đã kiểm tra, không có charset parameter và không tin MIME do client khai báo.
- Malware scan phải chạy trước upload, có timeout cấu hình và fail closed. `ERROR`, `TIMEOUT` hoặc `UNAVAILABLE` không được xem là CLEAN. Không được tuyên bố production ClamAV PASS nếu chưa kiểm tra PHP/queue worker thực tế.
- Object key phải do server sinh, tenant-scoped và không chứa tên file gốc. Metadata đã ghi provider nào thì provider đó là authoritative; cấm fallback im lặng sang disk khác.
- Signed URL phải được tạo sau authorization + tenant scope + object existence check, có thời hạn giới hạn và không được ghi log hoặc persist.
- Storage telemetry chỉ được ghi operation, provider, tenant hash, outcome, error classification, duration và correlation context; cấm ghi secret, signed URL, payload, private object key hoặc nội dung file.
- `documents:storage-reconcile` chỉ được chạy dry-run, bắt buộc `company_id`, giới hạn item/time, không in object key và không tự xóa object. Mọi cleanup/reconciliation live cần explicit approval mới.
- Mọi test có mutation chỉ được chạy trên database testing sau khi xác minh `APP_ENV`, configured DB và actual PDO DB. Không chạy nhiều suite `RefreshDatabase` song song trên cùng schema.
- Process concurrency chỉ được tuyên bố PASS khi đã chạy nhiều PHP process thật. Không được tuyên bố duplicate-idempotency hoặc deterministic collision PASS nếu API chưa có canonical idempotency key.
- Mỗi live S3 Put/Delete lifecycle cần explicit approval riêng, chỉ được dùng đúng một key mới theo runbook, dừng khi lỗi và cleanup đúng key đó. Không tự retry bằng key khác.
- Không thay đổi bucket, RLS, policy, grants, credentials, production database hoặc existing objects nếu chưa có audit, rollback plan và explicit approval tương ứng.
- Trước recovery/incident phải đọc `docs/runbooks/supabase-storage-failure.md`; không tắt scanner, không đổi sang public access và không fallback provider để che lỗi.
