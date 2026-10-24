<?php

namespace App\Support\DataQuality;

use JsonException;
use RuntimeException;

final readonly class RootOwnedStagingTrustEvidenceProvider implements StagingTrustEvidenceProvider
{
    public const APPROVAL_RECEIPT = '/etc/laraveldji/dq-staging-approval.json';

    public const RELEASE_IDENTITY = '/opt/laraveldji/release-identity.json';

    public const RESTORE_RECEIPT = '/etc/laraveldji/dq-staging-restore-receipt.json';

    public function __construct(
        private string $approvalPath = self::APPROVAL_RECEIPT,
        private string $releaseIdentityPath = self::RELEASE_IDENTITY,
        private string $restoreReceiptPath = self::RESTORE_RECEIPT,
    ) {}

    public function evidence(): array
    {
        [$approval, $approvalSha] = $this->readTrustedJson($this->approvalPath, 'approval receipt');
        [$release, $releaseSha] = $this->readTrustedJson($this->releaseIdentityPath, 'release identity');
        [$restore, $restoreSha] = $this->readTrustedJson($this->restoreReceiptPath, 'restore receipt');

        return [
            'approval' => $approval,
            'approval_sha256' => $approvalSha,
            'release' => $release,
            'release_identity_sha256' => $releaseSha,
            'restore' => $restore,
            'restore_receipt_sha256' => $restoreSha,
        ];
    }

    /** @return array{array<string,mixed>,string} */
    private function readTrustedJson(string $path, string $label): array
    {
        $this->assertCanonicalAbsolutePath($path, $label);
        $pathMetadata = $this->regularFileMetadata($path, $label);
        $this->assertTrustedParents($path, $label);

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Trusted '.$label.' cannot be opened.');
        }
        try {
            $metadata = $this->assertTrustedRegularFileMetadata(fstat($handle), $label);
            $this->assertSameFileIdentity($pathMetadata, $metadata, $label);
            $this->assertSameFileIdentity($this->trustedRegularFileMetadata($path, $label), $metadata, $label);
            $contents = stream_get_contents($handle);
            $this->assertSameFileIdentity($this->trustedRegularFileMetadata($path, $label), $metadata, $label);
        } finally {
            fclose($handle);
        }
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Trusted '.$label.' is empty or unreadable.');
        }

        try {
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Trusted '.$label.' is not valid JSON.', previous: $exception);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('Trusted '.$label.' root must be an object.');
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException('Trusted '.$label.' root must be an object.');
            }
            $result[$key] = $value;
        }

        return [$result, hash('sha256', $contents)];
    }

    private function assertTrustedParents(string $path, string $label): void
    {
        foreach ($this->lexicalParentPaths($path) as $directory) {
            clearstatcache(true, $directory);
            $metadata = @lstat($directory);
            if (! is_array($metadata) || $this->fileType($metadata) !== 0040000
                || $metadata['uid'] !== 0 || ($metadata['mode'] & 0022) !== 0) {
                throw new RuntimeException('Trusted '.$label.' parent path must be root-owned and not group/other writable.');
            }
        }
    }

    private function assertCanonicalAbsolutePath(string $path, string $label): void
    {
        if ($path === '' || str_contains($path, "\0") || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Trusted '.$label.' path must be canonical and absolute.');
        }
        $components = explode(DIRECTORY_SEPARATOR, substr($path, 1));
        if (in_array('', $components, true) || in_array('.', $components, true) || in_array('..', $components, true)) {
            throw new RuntimeException('Trusted '.$label.' path must be canonical and absolute.');
        }
    }

    /** @return list<string> */
    private function lexicalParentPaths(string $path): array
    {
        $parents = [DIRECTORY_SEPARATOR];
        $current = '';
        foreach (explode(DIRECTORY_SEPARATOR, trim(dirname($path), DIRECTORY_SEPARATOR)) as $component) {
            if ($component === '') {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR.$component;
            $parents[] = $current;
        }

        return $parents;
    }

    /** @return array<int|string,int>|never */
    private function trustedRegularFileMetadata(string $path, string $label): array
    {
        $metadata = $this->regularFileMetadata($path, $label);
        if ($metadata['uid'] !== 0 || ($metadata['mode'] & 0022) !== 0) {
            throw new RuntimeException('Trusted '.$label.' must be root-owned and not group/other writable.');
        }

        return $metadata;
    }

    /** @return array<int|string,int>|never */
    private function regularFileMetadata(string $path, string $label): array
    {
        clearstatcache(true, $path);
        $metadata = @lstat($path);
        if (! is_array($metadata) || $this->fileType($metadata) !== 0100000) {
            throw new RuntimeException('Trusted '.$label.' must be a regular non-symlink file.');
        }

        return $metadata;
    }

    /**
     * @param  array<int|string,int>|false  $metadata
     * @return array<int|string,int>
     */
    private function assertTrustedRegularFileMetadata(array|false $metadata, string $label): array
    {
        if (! is_array($metadata) || $this->fileType($metadata) !== 0100000) {
            throw new RuntimeException('Trusted '.$label.' must be a regular non-symlink file.');
        }
        if ($metadata['uid'] !== 0 || ($metadata['mode'] & 0022) !== 0) {
            throw new RuntimeException('Trusted '.$label.' must be root-owned and not group/other writable.');
        }

        return $metadata;
    }

    /**
     * @param  array<int|string,int>  $pathMetadata
     * @param  array<int|string,int>  $handleMetadata
     */
    private function assertSameFileIdentity(array $pathMetadata, array $handleMetadata, string $label): void
    {
        if ($pathMetadata['dev'] !== $handleMetadata['dev'] || $pathMetadata['ino'] !== $handleMetadata['ino']) {
            throw new RuntimeException('Trusted '.$label.' path/handle identity changed while reading.');
        }
    }

    /** @param array<int|string,int> $metadata */
    private function fileType(array $metadata): int
    {
        return $metadata['mode'] & 0170000;
    }
}
