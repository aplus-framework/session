<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\Redis;

/**
 * Class RedisTest.
 *
 * @runTestsInSeparateProcesses
 */
class RedisTest extends AbstractHandler
{
	public function setUp() : void
	{
		$this->config = [
			'host' => \getenv('REDIS_HOST'),
		];
		$this->handler = new Redis($this->config);
		parent::setUp();
	}
}
