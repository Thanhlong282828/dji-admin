# SCHEMA Admin (`dji-admin`)

Laravel 13 editorial CMS and content API for a premium imaging brand. Includes article workflow, Form Requests, API Resources, dashboard, Cloudflare R2-ready storage, Redis/queue infrastructure and production Docker.

## Local start

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
composer run dev
```

Open `http://localhost:8000`. Development seed: `admin@schema.vn` / `ChangeMe123!`. Breeze is installed; activate auth routes before public deployment.

Cloudflare R2 uses the S3 disk. Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, `AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com`, and `AWS_URL=https://<cdn-domain>`.

Quality: `php artisan test`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `docker compose config --quiet`.

See `db.txt`, `projec.txt`, and `luong.txt`. Websocket broadcasting, notifications, policies, scheduler jobs and CQRS folders are documented extension points requiring production credentials and business rules.
