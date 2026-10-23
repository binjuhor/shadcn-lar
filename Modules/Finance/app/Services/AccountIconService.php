<?php

namespace Modules\Finance\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Finance\Models\Account;

/**
 * Stores the single custom icon for an account.
 *
 * SVG uploads are sanitized first, then written to a temporary file so the media
 * library can detect the correct mime type from the file extension.
 */
class AccountIconService
{
    public function __construct(
        private SvgSanitizer $svgSanitizer,
        private BankIconLibrary $bankIconLibrary,
    ) {}

    public function store(Account $account, UploadedFile $file): void
    {
        $this->svgSanitizer->isSvg($file)
            ? $this->storeSvg($account, $file)
            : $this->storeRaster($account, $file);
    }

    /**
     * Copy a bank icon from the built-in library into the account's icon collection.
     *
     * @throws \InvalidArgumentException when the key does not resolve to a library icon
     * @throws \RuntimeException when the icon cannot be read or sanitized
     */
    public function storeFromLibrary(Account $account, string $key): void
    {
        $path = $this->bankIconLibrary->path($key);

        if ($path === null) {
            throw new \InvalidArgumentException("Unknown bank icon: {$key}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('The bank icon could not be read.');
        }

        $this->storeSanitizedSvg($account, $this->svgSanitizer->sanitizeString($contents));
    }

    public function remove(Account $account): void
    {
        $account->clearMediaCollection('icon');

        // Touch so the account payload (and its ETag) reflects the change.
        $account->touch();
    }

    private function storeRaster(Account $account, UploadedFile $file): void
    {
        $account->clearMediaCollection('icon');

        $account->addMedia($file)
            ->usingFileName($this->fileName($file))
            ->toMediaCollection('icon', config('media-library.disk_name'));

        // Touch so the account payload (and its ETag) reflects the change.
        $account->touch();
    }

    private function storeSvg(Account $account, UploadedFile $file): void
    {
        $sanitized = $this->svgSanitizer->sanitize($file);

        $this->storeSanitizedSvg($account, $sanitized);
    }

    private function storeSanitizedSvg(Account $account, string $sanitized): void
    {
        $tempPath = sys_get_temp_dir().'/'.Str::uuid().'.svg';
        file_put_contents($tempPath, $sanitized);

        try {
            $account->clearMediaCollection('icon');

            $account->addMedia($tempPath)
                ->usingFileName('icon.svg')
                ->toMediaCollection('icon', config('media-library.disk_name'));

            // Touch so the account payload (and its ETag) reflects the change.
            $account->touch();
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function fileName(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());

        return $extension !== '' ? 'icon.'.$extension : 'icon';
    }
}
