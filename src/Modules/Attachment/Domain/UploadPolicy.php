<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Domain;

use finfo;

/**
 * Which files may be attached. The type comes from the file's bytes
 * (finfo), not from the name or the browser. Anything that a browser could
 * run as a page or script (HTML, SVG, JS) is refused.
 */
final class UploadPolicy
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_PER_CARD = 50;
    public const MAX_PER_BOARD = 500 * 1024 * 1024;

    /** Detected type => extensions that may carry it. */
    private const ALLOWED = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'image/gif'       => ['gif'],
        'image/webp'      => ['webp'],
        'application/pdf' => ['pdf'],
        'text/plain'      => ['txt', 'md', 'log', 'csv'],
        'text/csv'        => ['csv'],
        'application/zip' => ['zip', 'docx', 'xlsx', 'pptx'],
        'application/msword' => ['doc'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.oasis.opendocument.text'        => ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
    ];

    /** The real type of the file if it is allowed under that name, otherwise null. */
    public function allowedType(string $path, string $clientName): ?string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!is_string($mime) || !in_array($extension, self::ALLOWED[$mime] ?? [], true)) {
            return null;
        }

        // A zip container is served as the Office type its name says.
        return match ($extension) {
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv'   => 'text/csv',
            default => $mime,
        };
    }

    /** A display name without paths, control characters or direction tricks, at most 150 characters. */
    public function cleanName(string $clientName): string
    {
        $name = basename(str_replace('\\', '/', $clientName));
        $name = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', '', $name);
        $name = trim($name, " .\t");

        if ($name === '') {
            return 'file';
        }
        if (mb_strlen($name) <= 150) {
            return $name;
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140) . ($extension !== '' ? '.' . mb_substr($extension, 0, 8) : '');
    }

    /** @return list<string> extensions for the file picker */
    public function extensions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ALLOWED))));
    }
}
