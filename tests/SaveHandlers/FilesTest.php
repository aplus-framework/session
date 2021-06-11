<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\Files;

/**
 * Class FilesTest.
 *
 * @runTestsInSeparateProcesses
 */
final class FilesTest extends AbstractHandler
{
	public function setUp() : void
	{
		$directory = \getenv('FILES_DIR');
		if ($directory && ! \is_dir($directory)) {
			\mkdir($directory, 0700, true);
		}
		$this->config = [
			'directory' => $directory,
		];
		$this->handler = new Files($this->config);
		parent::setUp();
	}
}
