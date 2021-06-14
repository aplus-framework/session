<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\FilesHandler;

/**
 * Class FilesTest.
 *
 * @runTestsInSeparateProcesses
 */
final class FilesHandlerTest extends AbstractHandler
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
		$this->handler = new FilesHandler($this->config);
		parent::setUp();
	}
}
