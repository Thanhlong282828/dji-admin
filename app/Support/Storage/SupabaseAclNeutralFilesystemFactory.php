<?php

namespace App\Support\Storage;

use Aws\CommandInterface;
use Aws\Middleware;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelS3Adapter;
use Illuminate\Support\Arr;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;

final class SupabaseAclNeutralFilesystemFactory
{
    /**
     * Supabase supports S3 object operations but not S3 ACL/grant headers.
     * This list is intentionally scoped to the dedicated Supabase client.
     *
     * @var list<string>
     */
    private const UNSUPPORTED_ACL_PARAMETERS = [
        'ACL',
        'GrantFullControl',
        'GrantRead',
        'GrantReadACP',
        'GrantWrite',
        'GrantWriteACP',
    ];

    /**
     * @param  array<array-key, mixed>  $config
     */
    public function create(array $config, ?S3Client $client = null): LaravelS3Adapter
    {
        $s3Config = $this->formatS3Config($config);
        $client ??= new S3Client($s3Config);

        $this->removeUnsupportedAclParameters($client);

        $root = is_string($s3Config['root'] ?? null) ? $s3Config['root'] : '';
        $visibility = new PortableVisibilityConverter(
            is_string($config['visibility'] ?? null) ? $config['visibility'] : Visibility::PRIVATE,
        );
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $streamReads = (bool) ($s3Config['stream_reads'] ?? false);
        $bucket = is_string($s3Config['bucket'] ?? null) ? $s3Config['bucket'] : '';

        $adapter = new AwsS3V3Adapter(
            $client,
            $bucket,
            $root,
            $visibility,
            null,
            $options,
            $streamReads,
        );

        $flysystem = new Filesystem($adapter, Arr::only($config, [
            'directory_visibility',
            'disable_asserts',
            'retain_visibility',
            'temporary_url',
            'url',
            'visibility',
        ]));

        return new LaravelS3Adapter($flysystem, $adapter, $s3Config, $client);
    }

    private function removeUnsupportedAclParameters(S3Client $client): void
    {
        $client->getHandlerList()->appendBuild(Middleware::mapCommand(
            static function (CommandInterface $command): CommandInterface {
                foreach (self::UNSUPPORTED_ACL_PARAMETERS as $parameter) {
                    unset($command[$parameter]);
                }

                return $command;
            },
        ), 'supabase-acl-neutral');
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<array-key, mixed>
     */
    private function formatS3Config(array $config): array
    {
        $config += ['version' => 'latest'];

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);

            if (! empty($config['token'])) {
                $config['credentials']['token'] = $config['token'];
            }
        }

        return Arr::except($config, ['token']);
    }
}
