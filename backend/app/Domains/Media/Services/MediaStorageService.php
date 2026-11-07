<?php

namespace App\Domains\Media\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload safety gate for the unified media library (Spec 035, T-D02).
 *
 * Every media payload must pass a real-content MIME sniff (finfo), an
 * image dimension probe for raster images, an explicit blocklist of
 * script/HTML MIME types, and a double-extension guard that stops
 * disguised executables such as `evil.php.jpg`.
 */
class MediaStorageService
{
    /** @var array<string, list<string>> */
    public const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'application/pdf' => ['pdf'],
    ];

    /** MIME types that are always rejected, even with a safe extension. */
    public const FORBIDDEN_MIMES = [
        'text/html',
        'image/svg+xml',
        'image/svg',
        'application/svg+xml',
        'application/javascript',
        'text/javascript',
        'application/x-httpd-php',
        'application/x-php',
        'text/x-php',
        'text/x-perl',
        'application/x-x509-ca-cert',
    ];

    /** File extensions that are never accepted in any position of the name. */
    public const FORBIDDEN_EXTENSIONS = [
        'php', 'phar', 'phtml', 'pht', 'php3', 'php4', 'php5', 'php7',
        'phps', 'sh', 'bash', 'htaccess', 'htpasswd', 'cgi', 'pl', 'py',
        'exe', 'bat', 'com', 'dll', 'cmd', 'msi', 'vbs', 'ps1', 'jar',
        'svg', 'svgz', 'html', 'htm', 'xhtml', 'js', 'mjs', 'shtml',
        'asp', 'aspx', 'jsp', 'cfm', 'sct', 'wsh', 'wsf',
    ];

    /**
     * Validate a candidate upload, returning its metadata on success.
     *
     * @return array{mime_type: string, extension: string, original_filename: string, original_name_safe: string, width: int|null, height: int|null}
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => [__('The upload failed or is corrupted.')]]);
        }

        $originalName = $file->getClientOriginalName();
        $this->assertNoForbiddenExtensionSequence($originalName);

        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        $extension = $extension === 'jpg' ? 'jpeg' : $extension;

        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => [__("Extension ':ext' is not allowed.", ['ext' => $extension])],
            ]);
        }

        $mime = $this->sniffMime($file);

        $this->assertMimeAllowed($mime, $file);

        $allowedExtensions = self::ALLOWED_MIME_EXTENSIONS[$mime];

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => [__("File extension ':ext' does not match detected MIME type ':mime'.", ['ext' => $extension, 'mime' => $mime])],
            ]);
        }

        [$width, $height] = $this->probeDimensions($mime, $file);

        $safeName = $this->sanitizeFilename($originalName, $extension);

        return [
            'mime_type' => $mime,
            'extension' => $extension,
            'original_filename' => $originalName,
            'original_name_safe' => $safeName,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function assertMimeAllowed(string $mime, UploadedFile $file): void
    {
        if (in_array($mime, self::FORBIDDEN_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => [__("File type ':mime' is not allowed.", ['mime' => $mime])],
            ]);
        }

        if (! array_key_exists($mime, self::ALLOWED_MIME_EXTENSIONS)) {
            throw ValidationException::withMessages([
                'file' => [__("File type ':mime' is not allowed.", ['mime' => $mime])],
            ]);
        }
    }

    private function sniffMime(UploadedFile $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($file->getRealPath());

        return is_string($detected) && $detected !== '' ? strtolower($detected) : 'application/octet-stream';
    }

    /**
     * Reject any filename where a forbidden extension appears in the
     * extension chain (e.g. `evil.php.jpg`, `payload.phtml`, `x.sh`).
     */
    private function assertNoForbiddenExtensionSequence(string $originalName): void
    {
        $parts = explode('.', strtolower(basename($originalName)));

        if (count($parts) < 2) {
            throw ValidationException::withMessages(['file' => ['An explicit file extension is required.']]);
        }

        $extensionChain = array_slice($parts, 1);

        foreach ($extensionChain as $part) {
            if ($part === '' && count($extensionChain) === 1) {
                throw ValidationException::withMessages(['file' => ['An explicit file extension is required.']]);
            }

            if (in_array($part, self::FORBIDDEN_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    'file' => [__("Extension '.:ext' is not allowed.", ['ext' => $part])],
                ]);
            }
        }
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function probeDimensions(string $mime, UploadedFile $file): array
    {
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [null, null];
        }

        $image = @getimagesize($file->getRealPath());

        if ($image === false) {
            throw ValidationException::withMessages([
                'file' => [__('The file does not contain a valid image.')],
            ]);
        }

        [$width, $height] = $image;

        return ((int) $width > 0 && (int) $height > 0) ? [(int) $width, (int) $height] : [null, null];
    }

    private function sanitizeFilename(string $originalName, string $extension): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = Str::slug($base);

        return ($slug === '' ? 'media-asset' : $slug) . '.' . $extension;
    }
}
