<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\Files;

/**
 * Class FilesTest.
 *
 * @runTestsInSeparateProcesses
 */
class FilesTest extends AbstractHandler
{
	public function setUp() : void
	{
		$directory = \getenv('FILES_DIR');
		if ( ! \is_dir($directory)) {
			\mkdir($directory, 0700, true);
		}
		$this->config = [
			'directory' => $directory,
		];
		$this->handler = new Files($this->config);
		parent::setUp();
	}
}
