<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * One file from a multipart form. The client's name and type are only
 * hints: callers must check the real content (see UploadPolicy).
 */
final class UploadedFile
{
    public function __construct(
        public readonly string $clientName,
        public readonly string $tmpPath,
        public readonly int $size,
        public readonly int $error = UPLOAD_ERR_OK,
        private bool $fromHttp = true,
    ) {
    }

    /** A file that did not come through HTTP, e.g. in tests or imports. */
    public static function local(string $path, string $clientName): self
    {
        return new self($clientName, $path, (int) filesize($path), UPLOAD_ERR_OK, false);
    }

    /**
     * Single-file fields only; "name[]" arrays are ignored.
     *
     * @param array<string, mixed> $files $_FILES
     * @return array<string, self>
     */
    public static function fromGlobals(array $files): array
    {
        $out = [];
        foreach ($files as $key => $file) {
            if (is_array($file) && is_string($file['name'] ?? null) && is_string($file['tmp_name'] ?? null)) {
                $out[(string) $key] = new self($file['name'], $file['tmp_name'], (int) ($file['size'] ?? 0), (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE));
            }
        }

        return $out;
    }

    public function isOk(): bool
    {
        return $this->error === UPLOAD_ERR_OK && ($this->fromHttp ? is_uploaded_file($this->tmpPath) : is_file($this->tmpPath));
    }

    public function isTooLargeForServer(): bool
    {
        return in_array($this->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    }

    public function moveTo(string $target): void
    {
        $moved = $this->fromHttp ? move_uploaded_file($this->tmpPath, $target) : copy($this->tmpPath, $target);
        if (!$moved) {
            throw new RuntimeException('Could not store the uploaded file.');
        }
    }
}
