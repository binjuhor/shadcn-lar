<?php

namespace Modules\Finance\Services;

/**
 * Reads the curated bank icon SVGs shipped with the app from resources/bank-icons
 * and exposes them as a browsable library.
 */
class BankIconLibrary
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('bank-icons');
    }

    /**
     * @return array<int, array{key: string, name: string, url: string}>
     */
    public function list(string $routeName = 'dashboard.finance.account-icons.show'): array
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        $files = glob($this->directory.'/*.svg') ?: [];

        $items = array_map(function (string $file) use ($routeName) {
            $filename = basename($file);

            return [
                'key' => $filename,
                'name' => $this->displayName($filename),
                'url' => route($routeName, ['file' => $filename]),
            ];
        }, $files);

        usort($items, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * Resolve a user-supplied key to an absolute path inside the library,
     * returning null when the key is missing or would escape the directory.
     */
    public function path(string $key): ?string
    {
        $basename = basename($key);

        if ($basename !== $key || ! preg_match('/\.svg$/i', $basename)) {
            return null;
        }

        $path = $this->directory.DIRECTORY_SEPARATOR.$basename;

        if (! is_file($path)) {
            return null;
        }

        $realDir = realpath($this->directory);
        $realPath = realpath($path);

        if ($realDir === false || $realPath === false
            || ! str_starts_with($realPath, $realDir.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    private function displayName(string $filename): string
    {
        $name = preg_replace('/\.svg$/i', '', $filename) ?? $filename;
        $name = str_replace(['-', '_'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', trim($name)) ?? $name;

        return $name;
    }
}
