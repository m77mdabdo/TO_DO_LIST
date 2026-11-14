<?php

declare(strict_types=1);

namespace App\Modules\Attachment\Domain;

use App\Http\UploadedFile;

/** Where attachment bytes live. Names are generated here, never taken from the user. */
interface FileStore
{
    /** Stores the upload and returns its internal name. */
    public function put(UploadedFile $file): string;

    /** Absolute path of a stored file, or null if it is missing. */
    public function path(string $storedName): ?string;

    public function delete(string $storedName): void;
}
