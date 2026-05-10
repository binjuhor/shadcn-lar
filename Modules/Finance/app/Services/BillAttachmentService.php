<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared file attachment helper for receipts/bills/screenshots.
 * Handles WebP conversion (smaller files, faster R2 reads) and graceful fallback
 * to the original file if conversion fails. Used by both the transaction form
 * and the smart-input flow.
 */
class BillAttachmentService
{
    /**
     * Attach one or more files to a media collection on the given model.
     * Silently logs and skips files that fail to upload — partial success is preferred
     * over rolling back the parent record.
     *
     * @param  UploadedFile|UploadedFile[]  $files
     */
    public function attach(HasMedia $model, string $collection, UploadedFile|array $files): void
    {
        $files = is_array($files) ? $files : [$files];

        foreach (array_filter($files) as $file) {
            $this->attachOne($model, $collection, $file);
        }
    }

    /**
     * Copy all media from one HasMedia model's collection to another model's collection.
     * Used when smart-input creates a transaction — the receipt image already attached to
     * the SmartInputHistory is copied (not moved) so both records keep their reference.
     */
    public function copyCollection(
        HasMedia $from,
        string $fromCollection,
        HasMedia $to,
        string $toCollection,
    ): void {
        $from->getMedia($fromCollection)->each(function (Media $media) use ($to, $toCollection) {
            try {
                $media->copy($to, $toCollection);
            } catch (\Throwable $e) {
                logger()->warning('Failed to copy media between models', [
                    'media_id' => $media->id,
                    'to_model' => $to::class,
                    'to_id' => $to->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    protected function attachOne(HasMedia $model, string $collection, UploadedFile $file): void
    {
        $webpPath = null;

        try {
            $webpPath = $this->convertToWebp($file);
        } catch (\Throwable $e) {
            logger()->warning('WebP conversion failed, uploading original', [
                'error' => $e->getMessage(),
            ]);
            report($e);
        }

        try {
            if ($webpPath && file_exists($webpPath) && filesize($webpPath) > 0) {
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.webp';

                $model->addMedia($webpPath)
                    ->usingFileName($originalName)
                    ->toMediaCollection($collection);
            } else {
                $model->addMedia($file)
                    ->preservingOriginal()
                    ->toMediaCollection($collection);
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            if ($webpPath && file_exists($webpPath)) {
                @unlink($webpPath);
            }
        }
    }

    /**
     * Convert an uploaded image to WebP. Returns the temp file path on success,
     * null when the source isn't an image or is already WebP (no point re-encoding).
     *
     * HEIC handling: GD can't read HEIC at all, and Imagick only can if built with
     * libheif. We try Imagick first (in case it works), then fall back to the
     * `heif-convert` CLI binary (libheif-examples package on Debian/Ubuntu) to
     * produce an intermediate JPEG, which we then encode to WebP.
     */
    public function convertToWebp(UploadedFile $file): ?string
    {
        $mime = $file->getMimeType();
        $sourcePath = $file->getPathname();

        if (! $mime || (! str_starts_with($mime, 'image/') && ! $this->isHeic($file))) {
            return null;
        }

        if ($mime === 'image/webp') {
            return null;
        }

        // HEIC needs a one-step transcode to JPEG first since neither GD nor most
        // GD/Imagick builds without libheif can decode it. After this branch,
        // $sourcePath either points to the original or to a temp JPEG.
        $heicJpegPath = null;
        if ($this->isHeic($file)) {
            $heicJpegPath = $this->heicToJpeg($sourcePath);
            if (! $heicJpegPath) {
                logger()->warning('HEIC received but no decoder available — uploading raw', [
                    'name' => $file->getClientOriginalName(),
                ]);

                return null;
            }
            $sourcePath = $heicJpegPath;
        }

        $tempPath = sys_get_temp_dir().'/'.Str::uuid().'.webp';

        $driver = config('media-library.image_driver', 'gd') === 'imagick'
            ? ImageDriver::Imagick
            : ImageDriver::Gd;

        try {
            Image::useImageDriver($driver)
                ->loadFile($sourcePath)
                ->format('webp')
                ->quality(85)
                ->save($tempPath);
        } finally {
            if ($heicJpegPath && file_exists($heicJpegPath)) {
                @unlink($heicJpegPath);
            }
        }

        return $tempPath;
    }

    protected function isHeic(UploadedFile $file): bool
    {
        $mime = $file->getMimeType();
        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            return true;
        }

        // Fall back to extension — some servers report HEIC as application/octet-stream.
        $ext = strtolower($file->getClientOriginalExtension());

        return in_array($ext, ['heic', 'heif'], true);
    }

    /**
     * Decode a HEIC file to a temp JPEG. Returns null when no decoder is available.
     * Tries (in order): PHP Imagick with libheif, the heif-convert CLI, and macOS sips.
     */
    protected function heicToJpeg(string $heicPath): ?string
    {
        $jpegPath = sys_get_temp_dir().'/'.Str::uuid().'.jpg';

        // 1. Imagick — works when PHP is built against ImageMagick + libheif.
        if (extension_loaded('imagick')) {
            $formats = @\Imagick::queryFormats('HEI*');
            if (! empty($formats)) {
                try {
                    $img = new \Imagick($heicPath);
                    $img->setImageFormat('jpeg');
                    $img->setImageCompressionQuality(92);
                    $img->writeImage($jpegPath);
                    $img->clear();

                    return $jpegPath;
                } catch (\Throwable $e) {
                    logger()->info('Imagick HEIC decode failed, trying CLI fallback', ['error' => $e->getMessage()]);
                }
            }
        }

        // 2. heif-convert (libheif-examples on Debian/Ubuntu).
        if ($this->commandExists('heif-convert')) {
            $cmd = sprintf('heif-convert %s %s 2>&1', escapeshellarg($heicPath), escapeshellarg($jpegPath));
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($jpegPath) && filesize($jpegPath) > 0) {
                return $jpegPath;
            }
            logger()->info('heif-convert failed', ['exit' => $exitCode, 'output' => implode("\n", $output)]);
        }

        // 3. sips (macOS — useful for local dev).
        if ($this->commandExists('sips')) {
            $cmd = sprintf(
                'sips -s format jpeg %s --out %s 2>&1',
                escapeshellarg($heicPath),
                escapeshellarg($jpegPath),
            );
            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($jpegPath) && filesize($jpegPath) > 0) {
                return $jpegPath;
            }
        }

        if (file_exists($jpegPath)) {
            @unlink($jpegPath);
        }

        return null;
    }

    protected function commandExists(string $cmd): bool
    {
        $which = trim((string) @shell_exec('command -v '.escapeshellarg($cmd).' 2>/dev/null'));

        return $which !== '';
    }
}
