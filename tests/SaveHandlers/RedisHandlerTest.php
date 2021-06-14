<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\RedisHandler;

/**
 * Class RedisTest.
 *
 * @runTestsInSeparateProcesses
 */
final class RedisHandlerTest extends AbstractHandler
{
	public function setUp() : void
	{
		$this->config = [
			'host' => \getenv('REDIS_HOST'),
		];
		$this->handler = new RedisHandler($this->config);
		parent::setUp();
	}
}
