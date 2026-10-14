<?php

namespace Tests\Concurrency\Document;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\MysqlProcessRaceRunner;
use Tests\TestCase;

#[Group('mysql-concurrency')]
final class DocumentStorageProcessConcurrencyTest extends TestCase
{
    public function test_two_processes_upload_distinct_immutable_versions_without_overwrite(): void
    {
        $fixture = $this->fixture();
        try {
            $payload = [
                'company_id' => $fixture['company'],
                'document_id' => $fixture['document'],
                'actor_id' => $fixture['actor']->id,
                'source_path' => $fixture['source'],
                'original_name' => 'process-race.pdf',
            ];
            $race = (new MysqlProcessRaceRunner)->run('enterprise_document_upload', $payload, $payload);

            self::assertSame([0, 0], $race['exit_codes'], implode("\n", $race['outputs']));
            $versions = DB::table('enterprise_document_versions')->where('document_id', $fixture['document'])->orderBy('version_number')->get();
            self::assertCount(2, $versions);
            self::assertSame([1, 2], $versions->pluck('version_number')->map(fn (mixed $value): int => $this->integer($value))->all());
            self::assertCount(2, $versions->pluck('storage_path')->unique());
            self::assertTrue($versions->every(static fn (object $row): bool => is_string($row->storage_path) && Storage::disk('local')->exists($row->storage_path)));
            $latestVersionId = DB::table('enterprise_document_versions')->where('document_id', $fixture['document'])->where('version_number', 2)->value('id');
            $currentVersionId = DB::table('enterprise_documents')->where('id', $fixture['document'])->value('current_version_id');
            self::assertSame($this->integer($latestVersionId), $this->integer($currentVersionId));
            self::assertSame(2, DB::table('activity_log')->where(['log_name' => 'enterprise-document', 'event' => 'version_uploaded'])->whereRaw("JSON_EXTRACT(properties, '$.document_id') = ?", [$fixture['document']])->count());
        } finally {
            $this->cleanup($fixture);
        }
    }

    /** @return array{company:int,employee:int,document:int,actor:User,source:string} */
    private function fixture(): array
    {
        $this->assertSafeDatabase();
        $token = Str::lower(Str::random(10));
        $actor = User::factory()->create(['email' => "document-race-{$token}@example.test"]);
        $company = (int) DB::table('companies')->insertGetId(['uuid' => (string) Str::uuid(), 'code' => 'DOC-'.$token, 'name' => 'Document Race', 'country_code' => 'VN', 'timezone' => 'Asia/Ho_Chi_Minh', 'currency_code' => 'VND', 'currency' => 'VND', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $employee = (int) DB::table('employees')->insertGetId(['uuid' => (string) Str::uuid(), 'company_id' => $company, 'employee_code' => 'E-'.$token, 'full_name' => 'Document Race', 'join_date' => today(), 'employment_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('company_user_access')->insert(['company_id' => $company, 'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $document = (int) DB::table('enterprise_documents')->insertGetId(['company_id' => $company, 'document_type' => 'IDENTITY_DOCUMENT', 'title' => 'Process race', 'classification' => 'RESTRICTED', 'owner_module' => 'Employee', 'subject_type' => 'EMPLOYEE', 'subject_id' => $employee, 'status' => 'ACTIVE', 'created_by' => $actor->id, 'updated_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $source = tempnam(sys_get_temp_dir(), 'document-race-');
        if (! is_string($source) || file_put_contents($source, "%PDF-1.4\nprocess race payload") === false) {
            throw new \RuntimeException('Unable to create document race fixture.');
        }

        return compact('company', 'employee', 'document', 'actor', 'source');
    }

    /** @param array{company:int,employee:int,document:int,actor:User,source:string} $fixture */
    private function cleanup(array $fixture): void
    {
        $paths = DB::table('enterprise_document_versions')->where('document_id', $fixture['document'])->pluck('storage_path');
        foreach ($paths as $path) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        DB::table('enterprise_documents')->where('id', $fixture['document'])->update(['current_version_id' => null]);
        DB::table('enterprise_document_versions')->where('document_id', $fixture['document'])->delete();
        DB::table('enterprise_documents')->where('id', $fixture['document'])->delete();
        DB::table('activity_log')->where('causer_id', $fixture['actor']->id)->delete();
        DB::table('company_user_access')->where(['company_id' => $fixture['company'], 'user_id' => $fixture['actor']->id])->delete();
        DB::table('employees')->where('id', $fixture['employee'])->delete();
        DB::table('companies')->where('id', $fixture['company'])->delete();
        $fixture['actor']->delete();
        @unlink($fixture['source']);
    }

    private function assertSafeDatabase(): void
    {
        self::assertSame('testing', app()->environment());
        self::assertSame('mysql', DB::connection()->getDriverName());
        self::assertSame('schema_admin_testing', DB::connection()->getDatabaseName());
    }

    private function integer(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new \RuntimeException('Expected a numeric database identifier.');
        }

        return (int) $value;
    }
}
