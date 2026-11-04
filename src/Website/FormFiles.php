<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Forms\Website;

use Frootbox\Core\Media\Documents;
use Frootbox\Core\Media\Uploads;
use Frootbox\Core\Storage\FileStores;
use Frootbox\Core\Storage\StorageException;
use Frootbox\Database\Ulid;
use Frootbox\Forms\Entry\Entries;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Files that visitors send with a form (decision 0230), e.g. an
 * application with a CV. They wait as uploads of the core until the form
 * is sent, then stay below private/forms/<entry>/ in the store for data
 * (storage/ or a bucket, decision 0268), where only signed-in users with
 * the permission for entries get them. Documents and
 * images only, at most MOST files of MAX_MB each and TOTAL_MB together.
 *
 * Deleting an entry can be undone, so its files stay a day after the
 * scheduler notices them without an entry.
 */
final readonly class FormFiles
{
    public const string DIRECTORY = 'private/forms';

    public const int MOST = 5;

    public const int MAX_MB = 10;

    public const int TOTAL_MB = 20;

    /** Media types visitors may send, with the extension of their files. */
    public const array TYPES = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'text/plain' => 'txt',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** For the attribute accept of the field. */
    public const string ACCEPT = '.pdf,.docx,.odt,.txt,.jpg,.jpeg,.png,.webp';

    /** A file the server did not take, e.g. too large for PHP. */
    public const string BROKEN = 'broken';

    private const string MARK = '.orphaned';

    private const int GRACE = 86400;

    public function __construct(
        private Uploads $uploads,
        private FileStores $stores,
    ) {}

    /**
     * Keeps an uploaded file as an upload of the core and returns its
     * token, or BROKEN.
     */
    public function receive(UploadedFileInterface $file): string
    {
        if (\UPLOAD_ERR_OK !== $file->getError()) {
            return self::BROKEN;
        }

        try {
            $path = $file->getStream()->getMetadata('uri');
        } catch (Throwable) {
            return self::BROKEN;
        }

        if (!\is_string($path) || !is_file($path)) {
            return self::BROKEN;
        }

        return $this->uploads->store($path, $file->getClientFilename() ?? 'file', move: is_uploaded_file($path));
    }

    /**
     * Name, size and media type of an upload, or null if there is none
     * or it is of a type not allowed.
     *
     * @return array{name: string, size: int, type: string}|null
     */
    public function inspect(string $token): ?array
    {
        $path = self::BROKEN === $token ? null : $this->uploads->path($token);

        if (null === $path) {
            return null;
        }

        $name = $this->uploads->name($token);
        $type = Documents::type($path, $name) ?? (string) new \finfo(\FILEINFO_MIME_TYPE)->file($path);

        return isset(self::TYPES[$type]) ? ['name' => $name, 'size' => (int) filesize($path), 'type' => $type] : null;
    }

    /**
     * Moves the uploads to the folder of the entry in the store for data
     * (decision 0268).
     *
     * @param list<string> $tokens
     *
     * @return list<array{id: string, name: string, size: int}>
     */
    public function keep(string $entryId, array $tokens): array
    {
        $kept = [];

        foreach ($tokens as $token) {
            $file = $this->inspect($token);
            $path = $this->uploads->path($token);

            if (null === $file || null === $path) {
                continue;
            }

            $id = strtolower(Ulid::generate()) . '.' . self::TYPES[$file['type']];

            try {
                $this->stores->storage()->writeFile($this->directory($entryId) . '/' . $id, $path, $file['type'], move: true);
            } catch (StorageException) {
                continue;
            }

            $this->uploads->remove($token);
            $kept[] = ['id' => $id, 'name' => $file['name'], 'size' => $file['size']];
        }

        return $kept;
    }

    /**
     * The path of a kept file in the store for data, null if there is none.
     */
    public function find(string $entryId, string $fileId): ?string
    {
        if (!Ulid::isValid($entryId) || 1 !== preg_match('/^[0-9a-z]{26}\.[a-z]{3,4}$/', $fileId)) {
            return null;
        }

        $path = $this->directory($entryId) . '/' . $fileId;

        return $this->stores->storage()->exists($path) ? $path : null;
    }

    public function size(string $path): int
    {
        return $this->stores->storage()->info($path)->size ?? 0;
    }

    /**
     * The bytes of a kept file as they are read.
     */
    public function stream(string $path): StreamInterface
    {
        return $this->stores->storage()->readRange($path);
    }

    /**
     * Removes the files of entries that are gone for a day, on every run
     * of the scheduler.
     *
     * @return int How many folders were removed
     */
    public function sweep(Entries $entries, ?int $now = null): int
    {
        $now ??= time();
        $removed = 0;
        $store = $this->stores->storage();
        $folders = [];

        foreach ($store->list(self::DIRECTORY . '/') as $file) {
            $parts = explode('/', substr($file->path, \strlen(self::DIRECTORY) + 1), 2);

            if (2 === \count($parts)) {
                $folders[$parts[0]][$parts[1]] = $file->modified;
            }
        }

        foreach ($folders as $id => $files) {
            $mark = $this->directory($id) . '/' . self::MARK;

            if (Ulid::isValid($id) && null !== $entries->fetchById($id)) {
                if (isset($files[self::MARK])) {
                    $store->delete($mark);
                }

                continue;
            }

            if (!isset($files[self::MARK])) {
                $store->write($mark, (string) $now);

                continue;
            }

            if ($now - self::marked($store->read($mark), $files[self::MARK]) >= self::GRACE) {
                $store->deletePrefix($this->directory($id) . '/');
                ++$removed;
            }
        }

        return $removed;
    }

    private function directory(string $entryId): string
    {
        return self::DIRECTORY . '/' . $entryId;
    }

    /**
     * When a folder got its mark: the time written into it, else its last
     * change (marks from before decision 0268 are empty).
     */
    private static function marked(?string $contents, int $modified): int
    {
        return null !== $contents && 1 === preg_match('/^\d+$/', trim($contents)) ? (int) trim($contents) : $modified;
    }
}
