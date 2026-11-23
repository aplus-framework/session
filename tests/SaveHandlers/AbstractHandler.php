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

use Aplus\Log\Logger;
use Aplus\Log\Loggers\MultiFileLogger;
use Tests\Session\TestCase;

/**
 * Class AbstractHandler.
 *
 * @runTestsInSeparateProcesses
 */
abstract class AbstractHandler extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];
    protected string $handlerClass;
    protected Logger $logger;

    public function setUp() : void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';
        $this->logger = new MultiFileLogger('/tmp');
        $this->handler = new $this->handlerClass($this->config, $this->logger); // @phpstan-ignore-line
        parent::setUp();
    }

    /**
     * @param array<string,mixed> $config
     */
    protected function replaceConfig(array $config) : void
    {
        $this->config = \array_replace_recursive($this->config, $config);
    }

    public function testGC() : void
    {
        $this->session->stop();
        $this->session->start([
            'gc_maxlifetime' => 1,
        ]);
        $this->session->foo = 'bar';
        self::assertSame($this->session->foo, 'bar');
        $this->session->stop();
        \sleep(2);
        self::assertIsInt($this->handler->gc(0));
        $this->session->start();
        self::assertNull($this->session->foo);
    }

    public function testRegenerate() : void
    {
        self::assertTrue($this->session->regenerateId());
        self::assertTrue($this->session->regenerateId(true));
    }

    public function testReset() : void
    {
        self::assertTrue($this->session->reset());
    }

    public function testGetConfig() : void
    {
        if (isset($this->config['match_ip'])) {
            self::assertSame(
                $this->config['match_ip'],
                $this->handler->getConfig('match_ip')
            );
        }
        self::assertNull($this->handler->getConfig('foo', true));
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Invalid config key: foo');
        $this->handler->getConfig('foo');
    }
}
