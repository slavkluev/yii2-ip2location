<?php

declare(strict_types=1);

namespace slavkluev\Ip2Location;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class UploaderTest extends TestCase
{
    protected $root;
    protected $uploader;
    protected $mockHandler;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('tmp');
        $this->mockHandler = new MockHandler(
            [
                new Response(
                    200,
                    [],
                    file_get_contents(__DIR__ . '/fixtures/IP2LOCATION-LITE-DB1.BIN.ZIP')
                ),
            ]
        );

        $httpClient = new Client([
            'handler' => HandlerStack::create($this->mockHandler),
        ]);

        $this->uploader = new Uploader($httpClient);
    }

    public function testUpdateWithoutRewrite()
    {
        $this->uploader->update('test', 'test', vfsStream::url('tmp/db.bin'));
        $this->assertFileEquals(__DIR__ . '/fixtures/IP2LOCATION-LITE-DB1.BIN', vfsStream::url('tmp/db.bin'));
    }

    public function testUpdateWithRewrite()
    {
        vfsStream::newFile('db.bin')
            ->withContent('test')
            ->at($this->root);
        $this->uploader->update('test', 'test', vfsStream::url('tmp/db.bin'));
        $this->assertFileEquals(__DIR__ . '/fixtures/IP2LOCATION-LITE-DB1.BIN', vfsStream::url('tmp/db.bin'));
    }

    public function testUpdateWithoutPermissions()
    {
        vfsStream::newFile('db.bin', 0000)
            ->withContent('test')
            ->at($this->root);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("failed to open stream");
        $this->uploader->update('test', 'test', vfsStream::url('tmp/db.bin'));
    }

    public function testUpdateWithoutSpace()
    {
        vfsStream::setQuota(10);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("possibly out of free disk space");
        $this->uploader->update('test', 'test', vfsStream::url('tmp/db.bin'));
    }
}