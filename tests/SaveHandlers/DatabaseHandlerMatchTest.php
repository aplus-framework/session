<?php namespace Tests\Session\SaveHandlers;

/**
 * Class DatabaseHandlerMatchTest.
 *
 * @runTestsInSeparateProcesses
 */
class DatabaseHandlerMatchTest extends DatabaseHandlerTest
{
	protected array $config = [
		'match_ip' => true,
		'match_ua' => true,
	];
}
