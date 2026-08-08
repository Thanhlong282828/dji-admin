# SCHEMA Admin audit report

Ngày kiểm tra: 2026-07-23. Database runtime và test: MySQL. Không chạy `migrate:fresh`, không xóa module/file hiện hữu.

## Kết luận điều hành

- Hoàn chỉnh ở mức đang chạy: Core CQRS buses; Dashboard read-side CQRS + Redis cache + permission + HRM cards; Company create/list CQRS; Breeze authentication; CMS Article CRUD một phần.
- Skeleton, đã vô hiệu hóa để không công khai route giả: Article, ArticleCategory, Attendance, Audit, Benefit, Contract, Document, Employee, Insurance, Integration, LaborRelation, Leave, Media, Menu, Notification, Offboarding, Onboarding, Organization, Page, Payroll, Performance, Position, Product, ProductCategory, Recruitment, Report, Security, Seo, Setting, Shift, Succession, System, Tax, Training, Workflow.
- Chưa đạt yêu cầu hoàn thành toàn hệ thống: 35 module trên chưa có domain implementation; Company thiếu update/delete/restore/status; Article controller còn truy cập Eloquent trực tiếp; realtime chưa có event broadcast theo module; test coverage theo module còn thiếu.

## Audit theo thành phần module

Scan nội dung file (không chỉ kiểm tra tên) cho thấy 37 module generator ban đầu chỉ có controller rỗng `store/update/destroy`, route resource và view index; không có migration/model/CQRS/repository/policy/event/listener/observer/notification/job/resource/request/test thực thi. Company là ngoại lệ có CQRS create/list. Core có Command Bus và Query Bus. Dashboard đã được thay bằng Query/Handler/Read Repository/DTO.

## Permission và route

- Dashboard: `auth`, `verified`, `can:dashboard.view`.
- CMS Article: permission riêng đúng action (`article.view`, `article.create`, `article.update`).
- Company API: Sanctum + permission hiện hữu từ module.
- Menu không còn link `#` hoặc `javascript:void(0)`; link thật ẩn bằng `@can`; module chưa làm hiển thị `Đang phát triển` và không clickable.
- Seeder hiện có 38 nhóm permission x 11 action, vai trò không hard-code trong controller.

## Database

- Runtime: `schema_admin` trên MySQL 9.6 cục bộ theo yêu cầu người dùng.
- Test: `schema_admin_testing` trên MySQL, tách biệt dữ liệu runtime.
- 7 migration runtime ở trạng thái `Ran`; không dùng SQLite trong PHPUnit.

## Docker

- Đã thay PostgreSQL sai kiến trúc bằng MySQL 8.4, Redis, PHP-FPM, Nginx, Queue, Scheduler, Horizon, Reverb và Mailpit.
- Có multi-stage Dockerfile, healthcheck, `docker-compose.dev.yml`, `docker-compose.prod.yml`, cấu hình PHP/MySQL/Nginx và ghi chú process supervision.
- `docker compose config --quiet`: đạt.
- `docker compose build`: chưa chạy được vì Docker Desktop daemon không hoạt động. Vì vậy chưa thể xác nhận `up`, `ps`, logs và container health.

## CI/CD

- Workflow có MySQL/Redis services, Composer validate/install/audit, npm build, Pint, PHPStan level 10, PHPUnit coverage, Compose validation, optimize, smoke test, Docker build/push, Trivy security scan, staging/production deploy và rollback hook.
- Deploy cần cấu hình GitHub secrets và script thật trên server: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`, `deploy.sh`, `rollback.sh`.
- PHPStan hiện còn 38 lỗi nên CI quality sẽ chủ động fail, không che lỗi bằng baseline.

## Kết quả lệnh

- `composer validate --strict`: đạt.
- `npm run build`: đạt.
- `vendor/bin/pint --test`: đạt trước thay đổi Blade-only cuối.
- `php artisan test`: 26/26 test, 64 assertions, đạt trên MySQL.
- `vendor/bin/phpstan analyse --memory-limit=1G`: fail, 38 lỗi.
- `docker compose config --quiet`: đạt.
- `docker compose build`: bị chặn bởi Docker daemon chưa chạy.
- Browser test `http://127.0.0.1:8001/dashboard`: đạt sau khi dọn Redis cache; dashboard HRM render cards, workforce intelligence, publishing và audit stream.

## File tạo mới

- Dashboard: DTO, Query, Handler, read repository interface/implementation, presentation controller.
- Docker: dev/prod compose, PHP ini, MySQL init, supervisor documentation.
- `AUDIT_REPORT.md`.

## File sửa chính

- `.env`, `.env.example`, `phpunit.xml`, `composer.json`, `composer.lock`.
- `routes/web.php`, `resources/views/layouts/admin.blade.php`, `resources/views/admin/dashboard/index.blade.php`.
- Dashboard service provider và module route files.
- `compose.yml`, `Dockerfile`, `.github/workflows/ci.yml`, `modules_statuses.json`.

## Ưu tiên tiếp theo

1. Sửa đủ 38 lỗi PHPStan level 10.
2. Refactor Article web/API sang CQRS và hoàn thiện full CRUD/restore/status/event/cache.
3. Hoàn thiện Company full CRUD + policy + event/job/test.
4. Triển khai lần lượt Core HR giai đoạn 1 thay vì bật lại skeleton.
5. Khởi động Docker Desktop rồi chạy build/up/ps/logs và health checks.
