# Technology Capability Inventory Governance

## Purpose

Chuẩn này quy định cách phát hiện, phân loại và báo cáo công nghệ/capability của LaravelDji. Đây là luật tái sử dụng; không phải snapshot phiên bản, nhật ký tiến độ hay bằng chứng production tại một thời điểm.

## Mandatory discovery sources

Mọi technology audit phải kiểm tra tối thiểu:

1. `composer.json`, `composer.lock`, `package.json` và lockfile frontend.
2. Source trong `app/`, `Modules/`, `routes/`, `config/` và `database/`.
3. Service-provider bindings, interfaces/adapters, middleware, commands, jobs, events và listeners.
4. Tests unit, feature, contract, concurrency, architecture và browser.
5. Docker/Compose, Nginx, PHP-FPM, Supervisor/systemd và health checks.
6. `.github/workflows`, deploy scripts, release/rollback scripts và supply-chain gates.
7. Storage, backup/restore, observability, security và incident runbooks.
8. Runtime evidence của đúng source/commit khi audit yêu cầu trạng thái vận hành.

Không được dựa vào một nguồn duy nhất. Tên class, package được cài hoặc config mẫu chỉ là tín hiệu để truy vết, không tự động chứng minh capability đang được sử dụng.

## Required capability domains

Inventory phải bao phủ, kể cả khi kết luận `NOT_PRESENT`:

- Runtime/framework, database và modular architecture.
- CQRS, repository/read model, transaction, locking, idempotency, events và outbox.
- Web/session auth, OAuth, scopes, RBAC/ABAC, Policy/Gate và company/branch isolation.
- Password hashing, HMAC, encryption, asymmetric signing, checksums, masking và log redaction.
- REST/OpenAPI, webhook signature/replay/deduplication và external-provider boundaries.
- Queue, scheduler, Horizon, realtime/broadcasting và worker lifecycle.
- Cache stores, locks, invalidation/versioning, TTL và tenant safety.
- Local/private/object storage, image provider, signed download, MIME/signature validation và malware scanning.
- Mail, notifications, PDF/spreadsheet exports và private artifacts.
- Blade/JavaScript/CSS/build tooling, responsive behavior, accessibility và browser automation.
- Static analysis, formatter, test frameworks, security/dependency/secret scans và coverage gates.
- Containers, reverse proxy, process manager, health/readiness and disaster-recovery runtime.
- CI/CD, image registry/scanning, immutable release identity, deployment gates, rollback and blue/green switching.
- Cloud identity, secret manager, object storage and external monitoring/error tracking.
- Backup encryption, checksums, restore rehearsal, RTO/RPO and business continuity.

## Evidence classification

Mỗi capability phải nhận đúng một trạng thái:

- `IMPLEMENTED_AND_VERIFIED`: có implementation và bằng chứng executable/runtime hiện hành đúng scope.
- `IMPLEMENTED_NOT_RUNTIME_VERIFIED`: source/test boundary tồn tại nhưng chưa có runtime evidence cần thiết.
- `CONFIGURED_OR_SUPPORTED`: framework/config có hỗ trợ nhưng chưa chứng minh application sử dụng hoặc provider hoạt động.
- `DOCUMENTATION_ONLY`: chỉ có design, governance hoặc runbook.
- `BLOCKED`: implementation có nhưng verification bị chặn; phải nêu blocker cụ thể.
- `NOT_PRESENT`: đã tìm trong các nguồn bắt buộc và không có bằng chứng implementation.

Không chuyển trạng thái dựa trên bằng chứng lịch sử của commit, môi trường hoặc artifact khác.

## Cryptographic classification

Không được gom chung dưới nhãn “security”:

- Password hashing dùng cho secret không cần khôi phục.
- HMAC dùng cho message authentication hoặc keyed fingerprint; phải xác minh algorithm, canonical bytes, key source, constant-time comparison, replay policy và rotation contract.
- Encryption dùng cho dữ liệu cần khôi phục; phải xác minh key lifecycle và re-encryption/rotation.
- Asymmetric signature/token signing phải xác minh private-key isolation và public-key verification boundary.
- SHA checksum chỉ chứng minh integrity khi trusted checksum/reference được bảo vệ; checksum không thay thế HMAC hoặc signature.
- Masking và redaction chỉ giảm hiển thị/rò rỉ; không thay thế encryption hoặc authorization.

Webhook audit phải kiểm tra raw/canonical payload, timestamp/replay window, event dedupe, secret lookup, rotation và failure behavior. Identity HMAC phải kiểm tra normalization, tenant/domain separation khi áp dụng và cấm log/export fingerprint nhạy cảm.

## Architecture truth rules

- LaravelDji được phân loại modular monolith trừ khi có bằng chứng service độc lập, deployment boundary và network contract thực tế.
- Event, queue, outbox hoặc module boundary không tự biến application thành microservices.
- Driver do Laravel hỗ trợ không đồng nghĩa driver đó đang dùng.
- Route/controller/table tồn tại không chứng minh workflow end-to-end.
- HTTP 200 không chứng minh authorization, tenant isolation hoặc dữ liệu đúng.
- Local PASS không thay thế CI, staging hoặc production verification.
- Monitoring page nội bộ không tự chứng minh Prometheus/Grafana/Alertmanager đã được provision.
- Interface với null/fake adapter không được báo là external provider đã tích hợp.
- Phone OTP không được báo là full MFA nếu thiếu enrollment, recovery và second-factor enforcement.

## Current baseline exclusions requiring explicit re-verification

Tại baseline được audit khi chuẩn này được tạo, chưa có đủ bằng chứng implementation để tuyên bố LaravelDji đang sử dụng các capability sau:

- Independent microservices.
- Kubernetes hoặc service orchestration tương đương.
- Helm.
- Terraform.
- Ansible.
- Kafka.
- RabbitMQ.
- NATS.
- GraphQL application API.
- Elasticsearch.
- OpenSearch.
- Meilisearch.
- Algolia.
- React.
- Vue.
- Angular.
- Svelte.
- Laravel Sanctum làm authentication mechanism chính.
- Custom JWT authentication làm authentication mechanism chính.
- WebAuthn hoặc passkey.
- Full TOTP MFA gồm enrollment, enforcement và recovery.
- Sentry hoặc Bugsnag provider thực; interface/null adapter không phải provider integration.
- Service mesh.
- Complete event sourcing architecture.

Baseline hiện tại phải được mô tả là **modular monolith với events, queue, bounded outbox usage và integration boundaries**, không phải microservices. Danh sách này là negative evidence của baseline đã audit, không phải lệnh cấm công nghệ. Trước mỗi báo cáo tương lai phải quét lại dependency, source, deployment và runtime evidence; nếu một capability được thêm hợp lệ thì cập nhật classification cùng architecture/security/operations contract thay vì tiếp tục lặp lại kết luận cũ.

## Change governance

Trước khi thêm hoặc thay công nghệ:

1. Xác định business/operational need và owner module.
2. Kiểm tra capability hiện có để tránh duplicate stack.
3. Lập threat model, data classification và tenant boundary.
4. Xác định failure, timeout, retry, idempotency, rollback và observability contract.
5. Cập nhật dependency/config qua canonical mechanism; không sửa vendor code.
6. Thêm positive, negative, authorization, tenant, failure và concurrency tests phù hợp.
7. Cập nhật architecture/security/operations documentation khi contract thay đổi.
8. Không kích hoạt provider, cloud resource, production credential hoặc data mutation nếu chưa có explicit approval.

## Reporting contract

Báo cáo technology inventory phải gồm:

`Capability → Evidence files → Owner → Data/security boundary → Evidence status → Runtime status → Known limitation → Required next action`

Phải có mục riêng liệt kê công nghệ không tìm thấy hoặc dễ bị hiểu nhầm. Không được trình bày framework-supported backend, planned integration hoặc historical test result như production capability hiện hành.
