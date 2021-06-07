<?php namespace Tests\Session\SaveHandlers;

use Framework\Database\Database;
use Framework\Database\Definition\Table\TableDefinition;
use Framework\Session\SaveHandlers\Database as DatabaseSaveHandler;

/**
 * Class DatabaseTest.
 *
 * @runTestsInSeparateProcesses
 */
class DatabaseTest extends AbstractHandler
{
	public function setUp() : void
	{
		$this->config = [
			'username' => \getenv('DB_USERNAME'),
			'password' => \getenv('DB_PASSWORD'),
			'schema' => \getenv('DB_SCHEMA'),
			'host' => \getenv('DB_HOST'),
			'port' => \getenv('DB_PORT'),
			'table' => \getenv('DB_TABLE'),
		];
		$this->handler = new DatabaseSaveHandler($this->config);
		$this->createDummyData();
		parent::setUp();
	}

	protected function createDummyData()
	{
		$database = new Database($this->config);
		$database->dropTable($this->config['table'])->ifExists()->run();
		$database->createTable($this->config['table'])
			->definition(static function (TableDefinition $definition) {
				$definition->column('id')->varchar(128)->primaryKey();
				$definition->column('ip')->varchar(45)->null();
				$definition->column('ua')->varchar(255)->null();
				$definition->column('timestamp')->int(10)->unsigned();
				$definition->column('data')->blob();
				$definition->index('ip')->key('ip');
				$definition->index('ua')->key('ua');
				$definition->index('timestamp')->key('timestamp');
			})->run();
	}
}
