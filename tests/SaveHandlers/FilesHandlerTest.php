<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\FilesHandler;

/**
 * Class FilesHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class FilesHandlerTest extends AbstractHandler
{
    protected string $handlerClass = FilesHandler::class;

    public function setUp() : void
    {
        $directory = \getenv('FILES_DIR');
        if ($directory && !\is_dir($directory)) {
            \mkdir($directory, 0700, true);
        }
        $this->replaceConfig([
            'directory' => $directory,
        ]);
        parent::setUp();
    }

    public function testNoDirectory() : void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Session config has not a directory');
        new FilesHandler();
    }

    public function testInvalidDirectory() : void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Session config directory does not exist: foo');
        new FilesHandler([
            'directory' => 'foo',
        ]);
    }

    public function testPrefix() : void
    {
        $config = [
            'prefix' => 'foo',
            'directory' => \getenv('FILES_DIR'),
        ];
        $handler = new class($config) extends FilesHandler {
            public function getFilename(string $id) : string
            {
                return parent::getFilename($id);
            }
        };
        self::assertSame(
            \getenv('FILES_DIR') . '/foo/ab/abc123',
            $handler->getFilename('abc123')
        );
    }

    public function testFailToWrite() : void
    {
        $handler = new class($this->config) extends FilesHandler {
            public $stream;
        };
        $handler->stream = null;
        self::assertFalse($handler->write('foo', 'bar'));
    }

    public function testUnlockWithoutStream() : void
    {
        $handler = new class($this->config) extends FilesHandler {
            public $stream;

            public function unlock() : bool
            {
                return parent::unlock();
            }
        };
        $handler->stream = null;
        self::assertTrue($handler->unlock());
    }
}
