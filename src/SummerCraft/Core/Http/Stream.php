<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * PSR-7 stream over a PHP resource.
 */
class Stream implements StreamInterface
{
    /**
     * @param resource $resource
     */
    private function __construct(
        /** @var resource|null */
        private $resource,
    ) {
    }

    public static function create(string $content = ''): self
    {
        $resource = fopen('php://temp', 'r+');
        if ($resource === false) {
            throw new RuntimeException('Can not open php://temp stream');
        }
        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }
        return new self($resource);
    }

    /**
     * @param resource $resource
     */
    public static function fromResource($resource): self
    {
        if (!is_resource($resource)) {
            throw new RuntimeException('Expected a valid stream resource');
        }
        return new self($resource);
    }

    public static function fromFile(string $filePath, string $mode = 'r'): self
    {
        $resource = @fopen($filePath, $mode);
        if ($resource === false) {
            throw new RuntimeException("Can not open file [$filePath] with mode [$mode]");
        }
        return new self($resource);
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }
        if ($this->isSeekable()) {
            $this->rewind();
        }
        return stream_get_contents($this->resource) ?: '';
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }
        $stats = fstat($this->resource);
        return $stats === false ? null : $stats['size'];
    }

    public function tell(): int
    {
        $position = $this->resource !== null ? ftell($this->resource) : false;
        if ($position === false) {
            throw new RuntimeException('Can not tell stream position');
        }
        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null && (bool)$this->getMetadata('seekable');
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Stream is not seekable');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        $mode = (string)$this->getMetadata('mode');
        return $this->resource !== null && strpbrk($mode, 'waxc+') !== false;
    }

    public function write(string $string): int
    {
        $written = $this->isWritable() ? fwrite($this->resource, $string) : false;
        if ($written === false) {
            throw new RuntimeException('Stream is not writable');
        }
        return $written;
    }

    public function isReadable(): bool
    {
        $mode = (string)$this->getMetadata('mode');
        return $this->resource !== null && strpbrk($mode, 'r+') !== false;
    }

    public function read(int $length): string
    {
        if ($length === 0) {
            return '';
        }
        $data = $this->isReadable() ? fread($this->resource, $length) : false;
        if ($data === false) {
            throw new RuntimeException('Stream is not readable');
        }
        return $data;
    }

    public function getContents(): string
    {
        $contents = $this->resource !== null ? stream_get_contents($this->resource) : false;
        if ($contents === false) {
            throw new RuntimeException('Can not read stream contents');
        }
        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }
        $metadata = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $metadata;
        }
        return $metadata[$key] ?? null;
    }
}
