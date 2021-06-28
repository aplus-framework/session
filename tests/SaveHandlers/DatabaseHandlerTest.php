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

use Framework\Database\Database;
use Framework\Database\Definition\Table\TableDefinition;
use Framework\Session\SaveHandlers\DatabaseHandler;
use Framework\Session\Session;

/**
 * Class DatabaseHandlerTest.
 *
 * @runTestsInSeparateProcesses
 */
class DatabaseHandlerTest extends AbstractHandler
{
	protected string $handlerClass = DatabaseHandler::class;

	public function setUp() : void
	{
		$this->replaceConfig([
			'username' => \getenv('DB_USERNAME'),
			'password' => \getenv('DB_PASSWORD'),
			'schema' => \getenv('DB_SCHEMA'),
			'host' => \getenv('DB_HOST'),
			'port' => \getenv('DB_PORT'),
			'table' => \getenv('DB_TABLE'),
		]);
		$this->createDummyData();
		parent::setUp();
	}

	protected function createDummyData() : void
	{
		$database = new Database($this->config);
		$database->dropTable($this->config['table'])->ifExists()->run();
		$database->createTable($this->config['table'])
			->definition(static function (TableDefinition $definition) : void {
				$definition->column('id')->varchar(128)->primaryKey();
				$definition->column('timestamp')->timestamp();
				$definition->column('data')->blob();
				$definition->column('ip')->varchar(45)->default('');
				$definition->column('ua')->varchar(255)->default('');
				$definition->index('timestamp')->key('timestamp');
				$definition->index('ip')->key('ip');
				$definition->index('ua')->key('ua');
			})->run();
	}

	public function testOpenError() : void
	{
		$this->session->stop();
		$handler = new DatabaseHandler([
			'username' => 'user-error',
			'password' => \getenv('DB_PASSWORD'),
			'host' => \getenv('DB_HOST'),
		], $this->logger);
		$session = new Session([], $handler);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Session could not be started');
		try {
			$session->start();
		} catch (\RuntimeException $exception) {
			self::assertMatchesRegularExpression(
				'#Session \(database\): Thrown a mysqli_sql_exception while trying to open: '
				. 'Access denied for user \'user-error\'@\'[0-9\.]+\' \(using password: YES\)#',
				$this->logger->getLastLog()->message
			);
			throw $exception;
		}
	}
}
