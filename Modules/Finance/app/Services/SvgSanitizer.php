<?php

namespace Modules\Finance\Services;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Strips scripts, event handlers, and remote references from uploaded SVGs.
 *
 * Uploaded SVG is served from a public URL, so an unsanitized file would be a
 * stored-XSS vector when opened directly. Rendering through an <img> tag is safe,
 * but we sanitize anyway so the raw asset cannot execute anything.
 */
class SvgSanitizer
{
    public function isSvg(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension === 'svg'
            || $file->getMimeType() === 'image/svg+xml';
    }

    /**
     * @throws RuntimeException when the file is not a usable SVG
     */
    public function sanitize(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('The SVG file could not be read.');
        }

        return $this->sanitizeString($contents);
    }

    /**
     * @throws RuntimeException when the input is not a usable SVG
     */
    public function sanitizeString(string $contents): string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(false);
        $sanitizer->minify(true);

        $clean = $sanitizer->sanitize($contents);

        if (! is_string($clean) || trim($clean) === '') {
            throw new RuntimeException('The SVG file could not be sanitized.');
        }

        return $clean;
    }
}
