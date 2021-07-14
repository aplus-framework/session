<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\SaveHandlers;

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
