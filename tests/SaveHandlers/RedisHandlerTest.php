<?php
/*
 * This file is part of The Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\SaveHandlers;

use Framework\Session\SaveHandlers\RedisHandler;

/**
 * Class RedisHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class RedisHandlerTest extends AbstractHandler
{
	protected string $handlerClass = RedisHandler::class;

	public function setUp() : void
	{
		$this->replaceConfig([
			'host' => \getenv('REDIS_HOST'),
		]);
		parent::setUp();
	}
}
