<?php namespace Tests\Session\SaveHandlers;

use Framework\Database\Database;

/**
 * Class DatabaseTest.
 *
 * @runTestsInSeparateProcesses
 */
class DatabaseTest extends AbstractHandler
{
	/**
	 * @var Database
	 */
	protected static $database;

	public function __construct(...$params)
	{
		$this->setDatabase();
		parent::__construct(...$params);
	}

	public function setUp()
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
		static::$database->exec('DROP TABLE IF EXISTS `Sessions`');
		static::$database->exec(
			<<<SQL
			CREATE TABLE IF NOT EXISTS `Sessions` (
			`id` varchar(128) NOT NULL,
			`ip` varchar(45),
			`ua` varchar(255),
			`timestamp` int(10) unsigned NOT NULL,
			`data` blob NOT NULL,
			PRIMARY KEY (`id`),
			KEY `ip` (`ip`),
			KEY `ua` (`ua`),
			KEY `timestamp` (`timestamp`)
			);
		SQL
		);
	}
}
