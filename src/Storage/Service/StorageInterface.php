<?php

namespace EMS\CommonBundle\Storage\Service;

use Psr\Http\Message\StreamInterface;

interface StorageInterface
{
    /** @var int  */
    public const STORAGE_USAGE_CACHE = 0;
    /** @var int  */
    public const STORAGE_USAGE_CONFIG = 1;
    /** @var int  */
    public const STORAGE_USAGE_ASSET = 2;
    /** @var int  */
    public const STORAGE_USAGE_BACKUP = 3;
    /** @var int  */
    public const STORAGE_USAGE_EXTERNAL = 4;

    public function head(string $hash): bool;

    public function health(): bool;

    public function __toString(): string;

    public function create(string $hash, string $filename): bool;

    public function read(string $hash, bool $confirmed = true): StreamInterface;

    public function getSize(string $hash): int;

    public function remove(string $hash): bool;

    public function initUpload(string $hash, int $size, string $name, string $type): bool;

    public function addChunk(string $hash, string $chunk): bool;

    public function finalizeUpload(string $hash): bool;

    public function isReadOnly(): bool;

    public function shouldSkip(): bool;
}
