# Employee PHPStan level 10

- Ban đầu: **83 lỗi** (`storage/logs/phpstan-employee-before.txt`).
- Sau Eloquent relation generics: **62 lỗi**.
- Sau command/query/repository/event/request typing: **23 lỗi**.
- Sau normalization và model-array boundary: **0 lỗi**.
- Sau khi thêm các luồng Phase 2.5B: **0 lỗi**.

Relations có generic chính xác; DTO/command/query typed; paginator và export generator có item shape; `mixed` được runtime-normalize tại boundary. Không dùng baseline, ignore, cast bừa hoặc hạ level.

```text
./vendor/bin/phpstan analyse Modules/Employee/app --level=10
[OK] No errors
```
