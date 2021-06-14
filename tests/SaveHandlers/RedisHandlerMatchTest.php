<?php namespace Tests\Session\SaveHandlers;

/**
 * Class RedisHandlerMatchTest.
 *
 * @runTestsInSeparateProcesses
 */
class RedisHandlerMatchTest extends RedisHandlerTest
{
	protected array $config = [
		'match_ip' => true,
		'match_ua' => true,
	];
}
