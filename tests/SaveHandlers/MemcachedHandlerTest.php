<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\MemcachedHandler;
use Framework\Session\Session;

/**
 * Class MemcachedTest.
 *
 * @runTestsInSeparateProcesses
 */
final class MemcachedHandlerTest extends AbstractHandler
{
	public function setUp() : void
	{
		$this->config = [
			'servers' => [
				[
					'host' => \getenv('MEMCACHED_HOST'),
				],
			],
		];
		$this->handler = new MemcachedHandler($this->config);
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
		]);
		$session = new Session([], $handler);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Session (memcached): Could not connect to any server');
		$session->start();
	}
}
