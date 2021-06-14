<?php namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\FilesHandler;

/**
 * Class FilesHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class FilesHandlerTest extends AbstractHandler
{
	protected string $handlerClass = FilesHandler::class;

	public function setUp() : void
	{
		$directory = \getenv('FILES_DIR');
		if ($directory && ! \is_dir($directory)) {
			\mkdir($directory, 0700, true);
		}
		$this->replaceConfig([
			'directory' => $directory,
		]);
		parent::setUp();
	}
}
