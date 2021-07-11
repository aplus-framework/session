<?php
/*
 * This file is part of The Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\MemcachedHandler;
use Framework\Session\Session;

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

    public function testNoServers() : void
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
}
