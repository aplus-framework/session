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

use Framework\Session\SaveHandlers\RedisHandler;
use Redis;

/**
 * Class RedisHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class RedisHandlerTest extends AbstractHandler
{
    protected string $handlerClass = RedisHandler::class;

    public function setUp() : void
    {
        $this->replaceConfig([
            'host' => \getenv('REDIS_HOST'),
        ]);
        parent::setUp();
    }

    public function testFailToConnect() : void
    {
        $this->replaceConfig([
            'host' => 'unknown',
        ]);
        $handler = new RedisHandler($this->config, $this->logger);
        self::assertFalse($handler->open('', ''));
        self::assertSame(
            'Session (redis): Could not connect to server unknown:6379',
            $this->logger->getLastLog()->message
        );
    }

    public function testFailToConnectWithPassword() : void
    {
        $this->replaceConfig([
            'password' => 'foo',
        ]);
        $handler = new RedisHandler($this->config, $this->logger);
        self::assertFalse($handler->open('', ''));
        self::assertSame(
            'Session (redis): Authentication failed',
            $this->logger->getLastLog()->message
        );
    }

    public function testFailToConnectWithDatabase() : void
    {
        $this->replaceConfig([
            'database' => 25,
        ]);
        $handler = new RedisHandler($this->config, $this->logger);
        self::assertFalse($handler->open('', ''));
        self::assertSame(
            "Session (redis): Could not select the database '25'",
            $this->logger->getLastLog()->message
        );
    }

    public function testFailToRead() : void
    {
        $handler = new class($this->config) extends RedisHandler {
            public ?Redis $redis;
        };
        $handler->redis = null;
        self::assertSame('', $handler->read('foo'));
    }

    public function testFailToWrite() : void
    {
        $handler = new class($this->config) extends RedisHandler {
            public ?Redis $redis;
            public ?string $sessionId;

            protected function unlock() : bool
            {
                return false;
            }
        };
        $handler->open('', '');
        $handler->sessionId = null;
        self::assertFalse($handler->write('foo', 'bar'));
        $handler->redis = null;
        self::assertFalse($handler->write('foo', 'bar'));
    }

    public function testFailToDestroy() : void
    {
        $handler = new RedisHandler($this->config);
        self::assertFalse($handler->destroy('foo'));
    }

    public function testFailToClose() : void
    {
        $handler = new class($this->config) extends RedisHandler {
            public ?Redis $redis;
        };
        $handler->open('', '');
        $redis = $handler->redis;
        $handler->redis = null;
        self::assertTrue($handler->close());
        $handler->redis = $redis;
        $handler->redis->close();
        self::assertTrue($handler->close());
        self::assertTrue($handler->close());
    }

    public function testUnlock() : void
    {
        $handler = new class($this->config, $this->logger) extends RedisHandler {
            public string | false $lockId;

            public function unlock() : bool
            {
                return parent::unlock();
            }
        };
        $handler->open('', '');
        $handler->lockId = false;
        self::assertTrue($handler->unlock());
        $handler->lockId = 'foo';
        self::assertFalse($handler->unlock());
        self::assertSame(
            'Session (redis): Error while trying to unlock foo',
            $this->logger->getLastLog()->message
        );
    }
}
