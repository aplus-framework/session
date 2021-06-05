<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\Memcached;

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
		$this->handler = new Memcached($this->config, true, true);
		parent::setUp();
	}
}
