<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Contract;

/**
 * A file or a link on a card. Files are kept outside the web root under a
 * random name; $storedName never leaves the server.
 */
final class Attachment
{
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly int $cardId,
        public readonly int $boardId,
        public readonly int $uploaderId,
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?string $mime,
        public readonly int $size,
        public readonly ?string $storedName,
        public readonly string $createdAt,
    ) {
    }

    public function isLink(): bool
    {
        return $this->kind === 'link';
    }

    public function isImage(): bool
    {
        return !$this->isLink() && in_array($this->mime, self::IMAGE_TYPES, true);
    }

    /** "1.4 MB", "820 KB" */
    public function readableSize(): string
    {
        return match (true) {
            $this->size >= 1048576 => round($this->size / 1048576, 1) . ' MB',
            $this->size >= 1024    => (int) round($this->size / 1024) . ' KB',
            default                => $this->size . ' B',
        };
    }
}
