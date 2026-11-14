<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Infrastructure;

use App\Http\UploadedFile;
use App\Modules\Attachment\Domain\FileStore;
use RuntimeException;

/**
 * Files on the local disk, outside the web root (storage/attachments by
 * default, which .htaccess also blocks). Names are 32 random hex characters
 * spread over 256 folders: ab/ab12...; nothing the user typed reaches the path.
 */
final class LocalFileStore implements FileStore
{
    public function __construct(private string $root)
    {
    }

    public function put(UploadedFile $file): string
    {
        $name = bin2hex(random_bytes(16));
        $dir = $this->root . '/' . substr($name, 0, 2);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create the attachments folder.');
        }
        $file->moveTo($dir . '/' . $name);
        @chmod($dir . '/' . $name, 0640);

        return $name;
    }

    public function path(string $storedName): ?string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $storedName) !== 1) {
            return null;
        }
        $path = $this->root . '/' . substr($storedName, 0, 2) . '/' . $storedName;

        return is_file($path) ? $path : null;
    }

    public function delete(string $storedName): void
    {
        $path = $this->path($storedName);
        if ($path !== null) {
            @unlink($path);
        }
    }
}
