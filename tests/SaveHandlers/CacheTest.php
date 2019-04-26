<?php namespace Tests\Session\SaveHandlers;

use Framework\Cache\Files;
use Framework\Session\SaveHandlers\Cache;

/**
 * Class CacheTest.
 *
 * @runTestsInSeparateProcesses
 */
class CacheTest extends AbstractHandler
{
	public function setUp()
	{
		$directory = \getenv('CACHE_DIR');
		\exec('rm -rf ' . $directory);
		\exec('mkdir -p ' . $directory);
		$instance = new Files([
			'directory' => $directory,
		]);
		$this->handler = new Cache($instance, true, true);
		parent::setUp();
	}
}
