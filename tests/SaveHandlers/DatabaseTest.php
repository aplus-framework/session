<?php namespace Tests\Session\SaveHandlers;

use Framework\Database\Database;
use Framework\Database\Definition\Table\TableDefinition;

/**
 * Class DatabaseTest.
 *
 * @runTestsInSeparateProcesses
 */
class DatabaseTest extends AbstractHandler
{
	protected static ?Database $database = null;

	public function __construct(...$params)
	{
		$this->setDatabase();
		parent::__construct(...$params);
	}

	public function setUp() : void
	{
		$this->handler = new \Framework\Session\SaveHandlers\Database(
			static::$database,
			true,
			true
		);
		parent::setUp();
	}

	protected function setDatabase() : Database
	{
		if (static::$database === null) {
			static::$database = new Database([
				'username' => \getenv('DB_USERNAME'),
				'password' => \getenv('DB_PASSWORD'),
				'schema' => \getenv('DB_SCHEMA'),
				'host' => \getenv('DB_HOST'),
				'port' => \getenv('DB_PORT'),
			]);
		}
		$this->createDummyData();
		return static::$database;
	}

	protected function createDummyData()
	{
		static::$database->dropTable('Sessions')->ifExists()->run();
		static::$database->createTable('Sessions')
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
