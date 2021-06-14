<?php namespace Tests\Session\SaveHandlers;

/**
 * Class FilesHandlerMatchTest.
 *
 * @runTestsInSeparateProcesses
 */
class FilesHandlerMatchTest extends FilesHandlerTest
{
	protected array $config = [
		'match_ip' => true,
		'match_ua' => true,
	];
}
