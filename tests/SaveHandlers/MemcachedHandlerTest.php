<?php namespace Tests\Session\SaveHandlers;

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
		]);
		$session = new Session([], $handler);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Session (memcached): Could not connect to any server');
		$session->start();
	}
}
