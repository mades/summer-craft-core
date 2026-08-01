<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\Http\Stream;

class StreamTest extends TestCase
{
    public function testCreateFromStringReadsBack(): void
    {
        $stream = Stream::create('hello');

        self::assertSame('hello', (string)$stream);
        self::assertSame(5, $stream->getSize());
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
    }

    public function testWriteAppendsAndTellTracksPosition(): void
    {
        $stream = Stream::create();
        $written = $stream->write('abc');

        self::assertSame(3, $written);
        self::assertSame(3, $stream->tell());

        $stream->rewind();
        self::assertSame(0, $stream->tell());
        self::assertSame('ab', $stream->read(2));
        self::assertSame('c', $stream->getContents());
        self::assertTrue($stream->eof());
    }

    public function testSeekMovesPosition(): void
    {
        $stream = Stream::create('abcdef');
        $stream->seek(2);
        self::assertSame('cdef', $stream->getContents());
    }

    public function testDetachMakesStreamUnusable(): void
    {
        $stream = Stream::create('data');
        $resource = $stream->detach();

        self::assertIsResource($resource);
        self::assertSame('', (string)$stream);
        self::assertNull($stream->getSize());
        self::assertFalse($stream->isReadable());
        fclose($resource);

        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    public function testCloseReleasesResource(): void
    {
        $stream = Stream::create('data');
        $stream->close();
        self::assertTrue($stream->eof());
        self::assertNull($stream->getMetadata('mode'));
    }

    public function testFromFileReadsFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stream-test');
        file_put_contents($file, 'file-content');
        try {
            $stream = Stream::fromFile($file);
            self::assertSame('file-content', (string)$stream);
            self::assertFalse($stream->isWritable());
        } finally {
            unlink($file);
        }
    }

    public function testFromMissingFileThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Stream::fromFile('/no/such/file-' . uniqid());
    }
}
