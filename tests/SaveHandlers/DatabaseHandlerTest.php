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
                $definition->column('user_id')->int(11)->null()->default(null);
                $definition->index('timestamp')->key('timestamp');
                $definition->index('ip')->key('ip');
                $definition->index('ua')->key('ua');
                $definition->index('user_id')->key('user_id');
            })->run();
    }

    public function testUserId() : void
    {
        $this->session->stop();
        $this->replaceConfig([
            'save_user_id' => true,
        ]);
        $handler = new class($this->config, $this->logger) extends DatabaseHandler {
            public ?Database $database;
        };
        $session = new Session(handler: $handler);
        $session->start();
        $database = $handler->database;
        $session->set('user_id', 123);
        $id = $session->id();
        $session->stop();
        $result = $database->select('user_id') // @phpstan-ignore-line
            ->from($this->config['table'])
            ->whereEqual('id', $id) // @phpstan-ignore-line
            ->run()
            ->fetch()->user_id;
        self::assertSame(123, $result);
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

    public function testFailToRead() : void
    {
        $handler = new class($this->config) extends DatabaseHandler {
            public ?Database $database;
        };
        $handler->database = null;
        self::assertSame('', $handler->read('foo'));
    }

    public function testFailToWrite() : void
    {
        $handler = new class($this->config) extends DatabaseHandler {
            public ?Database $database;
            public false | string $lockId;
        };
        $handler->database = null;
        self::assertFalse($handler->write('foo', 'data'));
        $handler->database = new Database($this->config);
        $handler->lockId = false;
        self::assertFalse($handler->write('foo', 'data'));
    }

    public function testFailToGC() : void
    {
        $handler = new DatabaseHandler([], $this->logger);
        self::assertFalse(@$handler->gc(3600));
        self::assertStringStartsWith(
            'Session (database): Thrown a mysqli_sql_exception',
            $this->logger->getLastLog()->message
        );
    }

    public function testUnlockWithoutLockId() : void
    {
        $handler = new class() extends DatabaseHandler {
            public function unlock() : bool
            {
                return parent::unlock();
            }
        };
        self::assertTrue($handler->unlock());
    }

    public function testFailToUnlock() : void
    {
        $handler = new class($this->config) extends DatabaseHandler {
            public false | string $lockId;

            public function unlock() : bool
            {
                return parent::unlock();
            }
        };
        $handler->open('', '');
        $handler->lockId = 'foo';
        self::assertFalse($handler->unlock());
    }

    public function testFailToLock() : void
    {
        $handler = new class($this->config, $this->logger) extends DatabaseHandler {
            public function lock(string $id) : bool
            {
                return parent::lock($id);
            }
        };
        $handler->open('', '');
        self::assertFalse($handler->lock(''));
        self::assertSame(
            'Session (database): Error while trying to lock',
            $this->logger->getLastLog()->message
        );
    }

    public function testDatabaseSetterAndGetter() : void
    {
        $handler = new DatabaseHandler($this->config);
        $database = new Database([
            'username' => \getenv('DB_USERNAME'),
            'password' => \getenv('DB_PASSWORD'),
            'host' => \getenv('DB_HOST'),
        ]);
        self::assertNull($handler->getDatabase());
        $handler->setDatabase($database);
        self::assertTrue($handler->open('', ''));
        self::assertSame($database, $handler->getDatabase());
        self::assertTrue($handler->close());
        self::assertSame($database, $handler->getDatabase());
    }
}
