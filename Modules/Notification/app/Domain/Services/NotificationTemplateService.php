<?php

namespace Modules\Notification\Domain\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationTemplateService
{
    public function create(int $companyId, int $actorId, string $code, string $name, string $category, string $subject, string $body, bool $mandatory): int
    {
        return DB::transaction(function () use ($companyId, $actorId, $code, $name, $category, $subject, $body, $mandatory): int {
            $id = (int) DB::table('notification_templates')->insertGetId(['company_id' => $companyId, 'uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'category' => $category, 'mandatory' => $mandatory, 'status' => 'draft', 'current_version' => 1, 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            $payload = ['subject' => $subject, 'body' => $body, 'version' => 1];
            DB::table('notification_template_versions')->insert(['notification_template_id' => $id, 'version' => 1, 'subject_template' => $subject, 'body_template' => $body, 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 3);
    }

    public function publish(int $companyId, int $templateId, int $actorId): void
    {
        DB::transaction(function () use ($companyId, $templateId, $actorId): void {
            $template = DB::table('notification_templates')->where('company_id', $companyId)->where('id', $templateId)->lockForUpdate()->first();
            abort_if($template === null, 404);
            if ($template->status === 'published') {
                return;
            }
            DB::table('notification_template_versions')->where('notification_template_id', $templateId)->where('version', $template->current_version)->update(['published_at' => now(), 'published_by' => $actorId, 'updated_at' => now()]);
            DB::table('notification_templates')->where('id', $templateId)->update(['status' => 'published', 'updated_at' => now()]);
        }, 3);
    }
}
