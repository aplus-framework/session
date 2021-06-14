<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\RedisHandler;

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
}
