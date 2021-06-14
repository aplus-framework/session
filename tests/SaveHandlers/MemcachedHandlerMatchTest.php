<?php namespace Tests\Session\SaveHandlers;

/**
 * Class MemcachedHandlerMatchTest.
 *
 * @runTestsInSeparateProcesses
 */
class MemcachedHandlerMatchTest extends MemcachedHandlerTest
{
	protected array $config = [
		'match_ip' => true,
		'match_ua' => true,
	];
}
