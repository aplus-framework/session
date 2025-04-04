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

use Framework\Session\SaveHandlers\MemcachedHandler;
use Framework\Session\Session;
use Memcached;

/**
 * Class MemcachedHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class MemcachedHandlerTest extends AbstractHandler
{
    protected string $handlerClass = MemcachedHandler::class;

    public function setUp() : void
    {
        $this->replaceConfig([
            'servers' => [
                [
                    'host' => \getenv('MEMCACHED_HOST'),
                ],
            ],
        ]);
        parent::setUp();
    }

    public function testNoHost() : void
    {
        $this->session->stop();
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage("Memcached host not set on server config '1'");
        new MemcachedHandler([
            'servers' => [
                [
                    'host' => 'localhost',
                ],
                [
                    'foo' => 'bar',
                ],
            ],
        ]);
    }

    public function testInvalidServers() : void
    {
        $this->session->stop();
        $handler = new MemcachedHandler([
            'servers' => [
                [
                    'host' => 'unknown',
                ],
            ],
        ], $this->logger);
        $session = new Session([], $handler);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session could not be started');
        try {
            $session->start();
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Session (memcached): Could not connect to any server',
                $this->logger->getLastLog()->message
            );
            throw $exception;
        }
    }

    public function testRepeatedServer() : void
    {
        $this->session->stop();
        $host = \getenv('MEMCACHED_HOST');
        (new MemcachedHandler([
            'servers' => [
                [
                    'host' => $host,
                ],
                [
                    'host' => $host,
                ],
            ],
        ], $this->logger))->open('', 'session_id');
        self::assertSame(
            'Session (memcached): Server pool already has ' . $host . ':11211',
            $this->logger->getLastLog()->message
        );
    }

    public function testInvalidOption() : void
    {
        $this->session->stop();
        @(new MemcachedHandler([
            'servers' => [
                [
                    'host' => \getenv('MEMCACHED_HOST'),
                ],
            ],
            'options' => [
                'foo' => 'bar',
            ],
        ], $this->logger))->open('', 'session_id');
        self::assertSame(
            'Memcached::setOptions(): invalid configuration option',
            \error_get_last()['message']
        );
    }

    public function testFailToRead() : void
    {
        $handler = new class($this->config) extends MemcachedHandler {
            public ?Memcached $memcached;
        };
        $handler->memcached = null;
        self::assertSame('', $handler->read('foo'));
    }

    public function testFailToWrite() : void
    {
        $handler = new class($this->config) extends MemcachedHandler {
            public ?Memcached $memcached;
            public false | string $lockId;
            public ?string $sessionId;
        };
        $handler->memcached = null;
        self::assertFalse($handler->write('foo', 'data'));
        $handler->open('', '');
        $handler->sessionId = null;
        $handler->lockId = '';
        self::assertFalse($handler->write('foo', 'data'));
        $handler->sessionId = 'foo';
        $handler->lockId = false;
        self::assertFalse($handler->write('foo', 'data'));
    }

    public function testUnlocked() : void
    {
        $handler = new class($this->config) extends MemcachedHandler {
            public false | string $lockId;

            public function unlock() : bool
            {
                return parent::unlock();
            }
        };
        $handler->open('', '');
        $handler->lockId = false;
        self::assertTrue($handler->unlock());
    }

    public function testReplaceLock() : void
    {
        $handler = new class($this->config, $this->logger) extends MemcachedHandler {
            public ?Memcached $memcached;
            public false | string $lockId;

            public function lock(string $id) : bool
            {
                return parent::lock($id);
            }
        };
        $handler->open('', '');
        $handler->lockId = 'foo';
        $handler->memcached->set('foo', 'bar', 15);
        self::assertTrue($handler->lock('x'));
    }

    public function testFailToDestroy() : void
    {
        $handler = new class($this->config) extends MemcachedHandler {
            public false | string $lockId;
        };
        $handler->lockId = false;
        self::assertFalse($handler->destroy('foo'));
    }

    public function testMemcachedSetterAndGetter() : void
    {
        $handler = new MemcachedHandler($this->config);
        $memcached = new Memcached();
        self::assertNull($handler->getMemcached());
        $handler->setMemcached($memcached);
        self::assertTrue($handler->open('', ''));
        self::assertSame($memcached, $handler->getMemcached());
        self::assertTrue($handler->close());
        self::assertSame($memcached, $handler->getMemcached());
    }
}
