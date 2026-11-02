# Environment Configuration Governance

Tài liệu này là quy tắc bắt buộc khi Codex hoặc developer thêm, sửa, kiểm tra hay vận hành cấu hình `.env` của LaravelDji ERP. `.env` là cấu hình runtime cục bộ/deployment và không phải nơi định nghĩa business logic.

## 1. Luật nền tảng

1. Không commit `.env`, token, access key, secret, mật khẩu, private key hoặc URL ký tạm thời.
2. Mọi biến được ứng dụng sử dụng phải có tên và giá trị mẫu an toàn trong `.env.example`; secret luôn để trống.
3. Chỉ `config/*` hoặc file config của module được gọi `env()`. Controller, service, command, job, Blade và JavaScript chỉ đọc `config()` hoặc contract đã inject.
4. Không đưa secret server-side vào biến có tiền tố `VITE_*`; mọi `VITE_*` có thể xuất hiện trong bundle trình duyệt.
5. Không in giá trị secret ra terminal, log, exception, UI, report hoặc tài liệu. Chỉ báo `configured/missing`, trạng thái HTTP đã redacted và thời điểm kiểm chứng.
6. Sau khi đổi `.env` hoặc config phải chạy `php artisan optimize:clear`; production chỉ cache config sau khi secret cuối cùng đã được inject.
7. Không suy đoán token còn hạn chỉ vì biến tồn tại. Việc xác minh cần endpoint read-only, không lộ token và không được thay đổi dữ liệu provider.
8. Không tạo fallback im lặng từ cloud về local nếu cấu hình cloud đã được chọn. Thiếu cấu hình phải fail closed với thông báo rõ ràng.

## 2. Database safety

- Runtime local thủ công: `APP_ENV=local`, database `schema_admin`.
- PHPUnit/integration: `APP_ENV=testing`, database `schema_admin_testing`.
- Browser test: database `schema_admin_browser_testing`.
- Không chạy destructive test, migration thử nghiệm hoặc fixture trên `schema_admin`.
- Trước mọi lệnh có mutation phải xác minh cả configured database và actual PDO database.
- Không bypass `EnvironmentSafetyGuard`.

## 3. Build và password hashing

### Session và cookie

```dotenv
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
```

- `SESSION_SECURE_COOKIE=false` chỉ phù hợp local HTTP. Production HTTPS phải đặt `true`.
- `SESSION_HTTP_ONLY=true` phải được giữ để JavaScript không đọc session cookie.
- `SESSION_SAME_SITE=lax` là default an toàn cho login/navigation thông thường; thay đổi sang `none` chỉ khi có cross-site flow thật và bắt buộc `Secure`.
- Không nới cookie scope/domain nếu không có deployment requirement cụ thể.
- Không đưa authentication/session logic sang frontend để bù cho cấu hình cookie sai.

### Vite source map

```dotenv
VITE_SOURCEMAP=false
```

- `vite.config.js` phải đọc cấu hình này qua cơ chế build hiện có.
- Production mặc định không phát hành source map công khai.
- Không xem đây là biện pháp thay thế cho việc loại bỏ secret khỏi frontend bundle.

### Password hashing

```dotenv
HASH_DRIVER=argon2id
```

- Chỉ dùng khi `config/hashing.php` và PHP runtime hiện tại hỗ trợ canonical driver này.
- Không đổi thuật toán trong controller hoặc auth flow.
- Không tự rehash hàng loạt user bằng script tùy tiện; Laravel rehash theo lifecycle đăng nhập/cơ chế chuẩn.

## 4. Webhook secrets

- Không dùng một `WEBHOOK_SECRET` toàn cục nếu integration architecture đang quản lý secret theo company/provider.
- Canonical inbound webhook secret hiện thuộc từng Integration Connection và được resolve qua Integration module/secret provider.
- Không thêm biến global chỉ để “đủ `.env`”; phải audit consumer thật trước.
- Luôn dùng signature verification, constant-time comparison, timestamp/replay guard và tenant/provider scope hiện có.

## 5. Cloudflare DDoS monitoring

Canonical variables:

```dotenv
CLOUDFLARE_ZONE_ID=
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_DDOS_MONITOR_ENABLED=true
CLOUDFLARE_DDOS_WINDOW_MINUTES=5
CLOUDFLARE_DDOS_EVENT_THRESHOLD=100
CLOUDFLARE_DDOS_TIMEOUT=10
CLOUDFLARE_UNDER_ATTACK_AUTO_ENABLE=true
```

Rules:

- Không sử dụng alias cũ `CLOUDFLARE_TOKEN`; canonical name là `CLOUDFLARE_API_TOKEN`.
- Token chỉ giới hạn đúng zone và chỉ có quyền cần thiết: Zone Read, Analytics Read và Zone Settings Edit khi tự bật Under Attack Mode.
- Monitor chỉ bật Under Attack Mode khi evidence vượt threshold và trạng thái hiện tại chưa bật; thao tác phải idempotent.
- Không tự động tắt Under Attack Mode. Việc hạ mức bảo vệ cần người vận hành xác minh và thực hiện theo runbook.
- Timeout và threshold đến từ config, không hard-code trong service.
- Không lưu token trong database hoặc gửi xuống browser.
- TTL của Cloudflare API token là thời hạn credential do Cloudflare quản lý, không phải cache TTL của Laravel. Với token vận hành tự động nên chọn thời hạn phù hợp chính sách rotation; phải có lịch xoay token và cảnh báo trước khi hết hạn, không chọn “không hết hạn” nếu tổ chức yêu cầu rotation.
- Runbook bắt buộc: `docs/runbooks/ddos-detected.md`.

Cloudflare Images là capability khác và dùng bộ biến riêng (`CLOUDFLARE_IMAGES_ACCOUNT_ID`, `CLOUDFLARE_IMAGES_API_TOKEN`, `CLOUDFLARE_IMAGES_DELIVERY_HASH`). Không dùng DDoS zone token thay cho Images token và không gộp hai permission scope.

## 6. Supabase Storage qua S3-compatible API

Canonical variables:

```dotenv
FILESYSTEM_DISK=local
DOCUMENT_STORAGE_DISK=supabase
EMPLOYEE_DOCUMENT_STORAGE_DISK=supabase
DOCUMENT_TEMPORARY_URL_MINUTES=5
SUPABASE_STORAGE_ENDPOINT=
SUPABASE_STORAGE_REGION=ap-southeast-1
SUPABASE_STORAGE_BUCKET=
SUPABASE_STORAGE_ACCESS_KEY_ID=
SUPABASE_STORAGE_SECRET_ACCESS_KEY=
DOCUMENT_CLAMAV_BINARY=clamscan
DOCUMENT_CLAMAV_DATABASE_DIRECTORY=/var/lib/clamav
DOCUMENT_CLAMAV_MAX_SIGNATURE_AGE_HOURS=48
DOCUMENT_RECONCILIATION_SCHEDULE_ENABLED=false
DOCUMENT_RECONCILIATION_COMPANIES_PER_RUN=25
DOCUMENT_RECONCILIATION_BATCH_MAX_SECONDS=240
```

Ownership and usage:

- `DOCUMENT_STORAGE_DISK` điều khiển Enterprise Document. Supabase S3 được dành cho tài liệu PDF, DOC/DOCX và XLS/XLSX (cùng image evidence hiện hữu); application phải xác minh chữ ký PDF/OLE hoặc cấu trúc OOXML trước upload. Bucket production chỉ được mở rộng MIME allowlist sau approval hạ tầng riêng; code không được fallback âm thầm sang local khi provider từ chối.
- `EMPLOYEE_DOCUMENT_STORAGE_DISK` điều khiển hồ sơ nhân viên riêng tư do Employee module sở hữu.
- `FILESYSTEM_DISK` không cần đổi sang Supabase chỉ để bật hai capability trên; mỗi bounded context chọn disk rõ ràng.
- Bucket phải private. Database chỉ lưu storage key/driver/checksum/metadata, không lưu URL signed và không lưu credential.
- Upload phải tenant-scoped (`company_id`, và `employee_id` khi áp dụng), dùng tên object ngẫu nhiên, checksum và MIME/signature validation.
- Hồ sơ nhân viên phải được malware scan trước khi upload cloud. File nhiễm không được đưa lên bucket.
- Nếu database transaction thất bại sau upload, service phải xóa object vừa tạo để tránh orphan.
- Download phải đi qua route permission/policy và tenant check trước khi tạo signed URL ngắn hạn.
- Signed URL không được persist; thời hạn phải bounded (hiện 1–60 phút, mặc định 5 phút).
- File nhân viên legacy không có `storage_disk` tiếp tục được đọc từ private local disk; không copy/clone dữ liệu tự động.
- Không tạo fake document, seed file hoặc live upload chỉ để chứng minh UI.
- VPS/container production phải có ClamAV executable và signature database mới, PHP/queue worker đọc được. Không tắt scanner để vượt deployment gate.
- Chạy `documents:storage-readiness --strict-production` trước khi chuyển traffic; command chỉ xuất boolean status, không xuất endpoint/key/secret.
- Reconciliation scheduler mặc định tắt. Khi bật, coordinator chỉ đọc company active theo cursor có giới hạn, sau đó gọi audit riêng cho từng `company_id`; không yêu cầu liệt kê 1.000 ID trong `.env`, không tạo storage scan xuyên tenant và không tự động xóa object.

## 7. Provider credential verification

Khi kiểm tra API/credential có hết hạn hay không:

1. Chỉ liệt kê tên biến và trạng thái `configured/missing`; không in giá trị.
2. Dùng endpoint read-only tối thiểu nếu được user cho phép truy cập mạng.
3. Phân biệt rõ `CONFIGURED`, `VERIFIED`, `EXPIRED/REVOKED`, `INSUFFICIENT_PERMISSION`, `NOT_VERIFIED`.
4. HTTP 401/invalid token thường là expired/revoked; HTTP 403 có thể là thiếu permission hoặc sai resource scope, không tự kết luận hết hạn.
5. Không tự rotate, tạo token mới, mở rộng permission hoặc thay secret nếu chưa được authorize.

## 8. Quy trình thêm biến mới

Trước khi thêm:

1. Tìm consumer thật bằng `rg` trong config, service, command, job và frontend.
2. Xác định owner module, loại dữ liệu (secret/non-secret), default và failure policy.
3. Nếu không có consumer/capability thật, không thêm biến “để dành”.

Khi triển khai:

1. Thêm placeholder vào `.env.example`.
2. Map bằng typed config với safe default.
3. Inject/read qua `config()`.
4. Thêm redaction nếu biến có thể xuất hiện trong observability/config audit.
5. Thêm focused test cho configured, missing và fail-closed behavior; external calls dùng fake trừ khi được phép live verify.
6. Cập nhật tài liệu owner/runbook liên quan.

Sau khi triển khai:

```bash
php artisan optimize:clear
vendor/bin/phpstan analyse --memory-limit=2G --no-progress
vendor/bin/pint --test
git diff --check
```

Chạy focused tests trên đúng testing database. Không dùng việc biến tồn tại trong `.env` làm bằng chứng rằng capability đã hoạt động end-to-end.

## 9. Trạng thái hiện tại cần giữ

- Supabase S3 là storage được chọn cho Enterprise Document và hồ sơ nhân viên riêng tư; default filesystem chung vẫn là local.
- Enterprise Document local hỗ trợ PDF, hợp đồng/evidence, Word, Excel và image. Supabase S3 là provider đích cho PDF/Word/Excel nhưng production vẫn fail-closed cho tới khi bucket MIME allowlist được xác minh độc lập. CSV/TXT không được đưa lên Supabase bằng document flow này.
- Hồ sơ nhân viên riêng tư dùng policy/tenant authorization, malware scanning, checksum, private object và signed download.
- Cloudflare DDoS monitor dùng `CLOUDFLARE_API_TOKEN`; có thể tự bật Under Attack Mode nhưng không tự tắt.
- Webhook secret tiếp tục thuộc Integration Connection, không dùng global `WEBHOOK_SECRET` khi chưa có canonical consumer.
- Không tài liệu nào được phép chứa giá trị credential thực tế.
