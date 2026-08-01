<?php

namespace SummerCraft\Core\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * PSR-7 uploaded file.
 */
class UploadedFile implements UploadedFileInterface
{
    private bool $moved = false;

    public function __construct(
        private ?string $tempFilePath,
        private ?int $size,
        private int $error,
        private ?string $clientFilename = null,
        private ?string $clientMediaType = null,
        private ?StreamInterface $stream = null,
    ) {
    }

    public function getStream(): StreamInterface
    {
        $this->assertAvailable();
        if ($this->stream !== null) {
            return $this->stream;
        }
        if ($this->tempFilePath === null) {
            throw new RuntimeException('Uploaded file has no stream or file path');
        }
        return Stream::fromFile($this->tempFilePath);
    }

    public function moveTo(string $targetPath): void
    {
        $this->assertAvailable();
        if ($targetPath === '') {
            throw new RuntimeException('Target path must not be empty');
        }

        if ($this->tempFilePath !== null) {
            // is_uploaded_file()/move_uploaded_file() only work in a real SAPI request
            $moved = PHP_SAPI === 'cli'
                ? rename($this->tempFilePath, $targetPath)
                : move_uploaded_file($this->tempFilePath, $targetPath);
        } else {
            $target = Stream::fromFile($targetPath, 'w');
            $source = $this->getStream();
            if ($source->isSeekable()) {
                $source->rewind();
            }
            while (!$source->eof()) {
                $target->write($source->read(65536));
            }
            $target->close();
            $moved = true;
        }

        if (!$moved) {
            throw new RuntimeException("Can not move uploaded file to [$targetPath]");
        }
        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    /**
     * Non-PSR extra: the SAPI temp file path, for path-based consumers.
     */
    public function getTempFilePath(): ?string
    {
        return $this->moved ? null : $this->tempFilePath;
    }

    private function assertAvailable(): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Uploaded file has error [{$this->error}]");
        }
        if ($this->moved) {
            throw new RuntimeException('Uploaded file was already moved');
        }
    }
}
