<?php

namespace SummerCraft\Core\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Core\Http\Stream;
use SummerCraft\Core\Http\UploadedFile;

class UploadedFileTest extends TestCase
{
    public function testStreamBasedFileExposesStream(): void
    {
        $file = new UploadedFile(null, 4, UPLOAD_ERR_OK, 'x.txt', 'text/plain', Stream::create('data'));

        self::assertSame('data', (string)$file->getStream());
        self::assertSame(4, $file->getSize());
        self::assertSame('x.txt', $file->getClientFilename());
        self::assertSame('text/plain', $file->getClientMediaType());
    }

    public function testMoveToWritesStreamContent(): void
    {
        $target = tempnam(sys_get_temp_dir(), 'upload-target');
        $file = new UploadedFile(null, 4, UPLOAD_ERR_OK, stream: Stream::create('data'));

        try {
            $file->moveTo($target);
            self::assertSame('data', file_get_contents($target));
        } finally {
            @unlink($target);
        }
    }

    public function testMoveToRenamesTempFileInCli(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'upload-src');
        file_put_contents($source, 'file-data');
        $target = $source . '-moved';
        $file = new UploadedFile($source, 9, UPLOAD_ERR_OK);

        try {
            $file->moveTo($target);
            self::assertFileDoesNotExist($source);
            self::assertSame('file-data', file_get_contents($target));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function testSecondMoveIsRejected(): void
    {
        $target = tempnam(sys_get_temp_dir(), 'upload-target');
        $file = new UploadedFile(null, 4, UPLOAD_ERR_OK, stream: Stream::create('data'));

        try {
            $file->moveTo($target);
            $this->expectException(RuntimeException::class);
            $file->moveTo($target);
        } finally {
            @unlink($target);
        }
    }

    public function testErroredUploadRefusesAccess(): void
    {
        $file = new UploadedFile(null, null, UPLOAD_ERR_NO_FILE);
        self::assertSame(UPLOAD_ERR_NO_FILE, $file->getError());

        $this->expectException(RuntimeException::class);
        $file->getStream();
    }
}
