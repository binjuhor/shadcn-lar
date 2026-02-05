<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PresignedUrlService
{
    private const ALLOWED_BUCKETS = [
        'uploads/images',
        'uploads/avatars',
        'uploads/attachments',
    ];

    private const DEFAULT_EXPIRY_MINUTES = 10;

    private const MAX_FILE_SIZE = 10485760; // 10MB

    public function generateUploadUrl(
        string $bucketId,
        string $filename,
        string $mimeType,
        int $contentLength,
        bool $unique = true,
    ): array {
        $this->validateBucketId($bucketId);
        $this->validateContentLength($contentLength);

        $path = $this->buildPath($bucketId, $filename, $unique);
        $client = $this->getS3Client();
        $bucket = config('filesystems.disks.r2.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $path,
            'ContentType' => $mimeType,
            'ContentLength' => $contentLength,
        ]);

        $presignedRequest = $client->createPresignedRequest(
            $command,
            "+".self::DEFAULT_EXPIRY_MINUTES." minutes"
        );

        return [
            'path' => $path,
            'url' => (string) $presignedRequest->getUri(),
            'headers' => [
                'Content-Type' => $mimeType,
            ],
        ];
    }

    private function validateBucketId(string $bucketId): void
    {
        if (! in_array($bucketId, self::ALLOWED_BUCKETS, true)) {
            throw new InvalidArgumentException(
                "Invalid bucket ID: {$bucketId}. Allowed: ".implode(', ', self::ALLOWED_BUCKETS)
            );
        }
    }

    private function validateContentLength(int $contentLength): void
    {
        if ($contentLength <= 0 || $contentLength > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                "Content length must be between 1 and ".self::MAX_FILE_SIZE." bytes."
            );
        }
    }

    private function buildPath(string $bucketId, string $filename, bool $unique): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $slug = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        $date = now();

        $name = $unique
            ? Str::uuid()."_{$slug}"
            : $slug;

        return "{$bucketId}/{$date->format('Y')}/{$date->format('m')}/{$name}.{$extension}";
    }

    private function getS3Client(): S3Client
    {
        /** @var \Illuminate\Filesystem\AwsS3V3Adapter $disk */
        $disk = Storage::disk('r2');

        return $disk->getClient();
    }
}
