<?php

namespace Tests\Unit\Storage;

use App\Support\Storage\SupabaseAclNeutralFilesystemFactory;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as LeagueS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;

class SupabaseAclNeutralFilesystemTest extends TestCase
{
    /** @var list<string> */
    private const ACL_PARAMETERS = [
        'ACL',
        'GrantFullControl',
        'GrantRead',
        'GrantReadACP',
        'GrantWrite',
        'GrantWriteACP',
    ];

    public function test_put_object_and_stream_upload_are_acl_neutral(): void
    {
        $commands = [];
        $capture = static function (CommandInterface $command) use (&$commands): Result {
            $commands[] = self::commandEvidence($command);

            return new Result;
        };
        $disk = $this->disk(new MockHandler([$capture, $capture]));

        self::assertTrue($disk->put(
            'enterprise-documents/7/15/document.pdf',
            'document-body',
            [
                'visibility' => 'private',
                'ACL' => 'public-read',
                'GrantRead' => 'should-be-removed',
                'GrantFullControl' => 'should-be-removed',
            ],
        ));

        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, 'stream-body');
        rewind($stream);

        self::assertTrue($disk->writeStream(
            'employees/7/22/documents/private.pdf',
            $stream,
            ['visibility' => 'private', 'GrantWriteACP' => 'should-be-removed'],
        ));
        fclose($stream);

        self::assertCount(2, $commands);
        foreach ($commands as $command) {
            self::assertSame('PutObject', $command['name']);
            self::assertSame([], $command['acl_parameters']);
        }
    }

    public function test_minimal_pdf_uses_exact_pdf_content_type_without_acl_or_grant_headers(): void
    {
        $commands = [];
        $capture = static function (CommandInterface $command) use (&$commands): Result {
            $commands[] = self::commandEvidence($command);

            return new Result;
        };
        $disk = $this->disk(new MockHandler([$capture]));
        $pdf = self::minimalPdf();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertTrue($disk->put(
            'security-verification/mock/minimal.pdf',
            $pdf,
            ['ContentType' => 'application/pdf'],
        ));

        self::assertCount(1, $commands);
        self::assertSame('PutObject', $commands[0]['name']);
        self::assertSame('application/pdf', $commands[0]['content_type']);
        self::assertSame([], $commands[0]['acl_parameters']);
    }

    public function test_read_head_and_delete_operations_still_use_the_s3_adapter(): void
    {
        $commands = [];
        $responses = [
            new Result(['Body' => Utils::streamFor('stored-body')]),
            new Result(['ContentLength' => 11]),
            new Result,
        ];
        $capture = static function (CommandInterface $command) use (&$commands, &$responses): Result {
            $commands[] = self::commandEvidence($command);

            return array_shift($responses) ?? new Result;
        };
        $disk = $this->disk(new MockHandler([$capture, $capture, $capture]));

        self::assertSame('stored-body', $disk->get('enterprise-documents/7/15/document.pdf'));
        self::assertTrue($disk->exists('enterprise-documents/7/15/document.pdf'));
        self::assertTrue($disk->delete('enterprise-documents/7/15/document.pdf'));

        self::assertSame(
            ['GetObject', 'HeadObject', 'DeleteObject'],
            array_column($commands, 'name'),
        );
    }

    public function test_transport_exception_is_wrapped_without_falling_back_or_persisting_metadata(): void
    {
        $client = $this->client(new MockHandler);
        $command = $client->getCommand('PutObject', [
            'Bucket' => 'laraveldji-media',
            'Key' => 'enterprise-documents/7/15/failure.pdf',
            'Body' => 'body',
        ]);
        $mock = new MockHandler([
            new AwsException('redacted transport failure', $command),
        ]);
        $disk = $this->disk($mock);

        $this->expectException(UnableToWriteFile::class);
        $disk->put('enterprise-documents/7/15/failure.pdf', 'body');
    }

    public function test_only_supabase_disk_uses_the_custom_driver(): void
    {
        self::assertSame('supabase-s3', config('filesystems.disks.supabase.driver'));
        self::assertSame('s3', config('filesystems.disks.s3.driver'));
        self::assertSame('local', config('filesystems.disks.local.driver'));
    }

    public function test_laravel_resolves_the_acl_neutral_driver_for_supabase(): void
    {
        config()->set('filesystems.disks.supabase', $this->config());
        Storage::forgetDisk('supabase');

        self::assertInstanceOf(AwsS3V3Adapter::class, Storage::disk('supabase'));

        Storage::forgetDisk('supabase');
    }

    public function test_standard_s3_adapter_remains_unchanged(): void
    {
        $commands = [];
        $capture = static function (CommandInterface $command) use (&$commands): Result {
            $commands[] = self::commandEvidence($command);

            return new Result;
        };
        $client = $this->client(new MockHandler([$capture]));
        $filesystem = new Filesystem(new LeagueS3Adapter($client, 'standard-s3-bucket'));

        $filesystem->write('unrelated.txt', 'body');

        self::assertSame('PutObject', $commands[0]['name']);
        self::assertSame(['ACL' => 'private'], $commands[0]['acl_parameters']);
    }

    private function disk(MockHandler $mock): AwsS3V3Adapter
    {
        $client = $this->client($mock);

        return (new SupabaseAclNeutralFilesystemFactory)->create($this->config(), $client);
    }

    /** @return array{name: string, content_type: mixed, acl_parameters: array<string, mixed>} */
    private static function commandEvidence(CommandInterface $command): array
    {
        $parameters = [];
        foreach (self::ACL_PARAMETERS as $parameter) {
            if ($command->hasParam($parameter)) {
                $parameters[$parameter] = $command[$parameter];
            }
        }

        return [
            'name' => $command->getName(),
            'content_type' => $command->hasParam('ContentType') ? $command['ContentType'] : null,
            'acl_parameters' => $parameters,
        ];
    }

    private static function minimalPdf(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R /Resources << >> >>',
            "<< /Length 0 >>\nstream\n\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function client(MockHandler $mock): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'ap-southeast-1',
            'credentials' => ['key' => 'local-test', 'secret' => 'local-test'],
            'endpoint' => 'https://example.invalid/storage/v1/s3',
            'use_path_style_endpoint' => true,
            'handler' => $mock,
        ]);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'driver' => 'supabase-s3',
            'key' => 'local-test',
            'secret' => 'local-test',
            'region' => 'ap-southeast-1',
            'bucket' => 'laraveldji-media',
            'endpoint' => 'https://example.invalid/storage/v1/s3',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => true,
        ];
    }
}
