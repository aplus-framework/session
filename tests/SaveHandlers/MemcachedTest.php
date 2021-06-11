<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\Memcached;
use Framework\Session\Session;

/**
 * Class MemcachedTest.
 *
 * @runTestsInSeparateProcesses
 */
class MemcachedTest extends AbstractHandler
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
		$this->handler = new Memcached($this->config);
		parent::setUp();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testNoServers()
	{
		$this->session->stop();
		$handler = new Memcached([
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
