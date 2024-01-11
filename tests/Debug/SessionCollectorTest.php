<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session\Debug;

use Framework\Database\Database;
use Framework\Database\Definition\Table\TableDefinition;
use Framework\Session\Debug\SessionCollector;
use Framework\Session\SaveHandler;
use Framework\Session\SaveHandlers\DatabaseHandler;
use Framework\Session\SaveHandlers\FilesHandler;
use Framework\Session\SaveHandlers\MemcachedHandler;
use Framework\Session\SaveHandlers\RedisHandler;
use Framework\Session\Session;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class SessionCollectorTest extends TestCase
{
    protected SessionCollector $collector;

    protected function setUp() : void
    {
        $this->collector = new SessionCollector();
    }

    /**
     * @param array<string,int|string> $options
     * @param SaveHandler|null $handler
     *
     * @return Session
     */
    protected function makeSession(array $options = [], SaveHandler $handler = null) : Session
    {
        $session = new Session($options, $handler);
        $session->setDebugCollector($this->collector);
        return $session;
    }

    public function testNoSession() : void
    {
        self::assertStringContainsString(
            'No Session instance has been set',
            $this->collector->getContents()
        );
    }

    public function testSessionInactive() : void
    {
        $session = new Session();
        $session->setDebugCollector($this->collector);
        self::assertStringContainsString(
            'Session is inactive',
            $this->collector->getContents()
        );
    }

    public function testData() : void
    {
        $session = $this->makeSession();
        $session->start();
        self::assertStringContainsString(
            'No data',
            $this->collector->getContents()
        );
        $session->set('foo', 'bar');
        self::assertStringNotContainsString(
            'No data',
            $this->collector->getContents()
        );
        self::assertStringContainsString(
            'foo',
            $this->collector->getContents()
        );
    }

    public function testFlashData() : void
    {
        $session = $this->makeSession();
        $session->start();
        self::assertStringContainsString(
            'No flash data',
            $this->collector->getContents()
        );
        $session->setFlash('foo', 'bar');
        self::assertStringNotContainsString(
            'No flash data',
            $this->collector->getContents()
        );
        self::assertStringContainsString(
            'foo',
            $this->collector->getContents()
        );
        $session->stop();
        $session->start();
        self::assertStringContainsString(
            'foo',
            $this->collector->getContents()
        );
    }

    public function testTempData() : void
    {
        $session = $this->makeSession();
        $session->start();
        self::assertStringContainsString(
            'No temp data',
            $this->collector->getContents()
        );
        $session->setTemp('foo', 'bar');
        self::assertStringNotContainsString(
            'No temp data',
            $this->collector->getContents()
        );
        self::assertStringContainsString(
            'foo',
            $this->collector->getContents()
        );
    }

    public function testAutoRegenerateId() : void
    {
        $session = $this->makeSession();
        $session->start();
        self::assertStringContainsString(
            'Auto regenerate id is inactive',
            $this->collector->getContents()
        );
        self::assertStringNotContainsString(
            'Regenerated At',
            $this->collector->getContents()
        );
        $session->stop();
        $session = $this->makeSession(['auto_regenerate_maxlifetime' => 10]);
        $session->start();
        self::assertStringNotContainsString(
            'Auto regenerate id is inactive',
            $this->collector->getContents()
        );
        self::assertStringContainsString(
            'Regenerated At',
            $this->collector->getContents()
        );
        unset($_SESSION['$']);
        self::assertStringNotContainsString(
            'Auto regenerate id is inactive',
            $this->collector->getContents()
        );
        self::assertStringContainsString(
            'Regenerated At',
            $this->collector->getContents()
        );
    }

    /**
     * @dataProvider saveHandlerProvider
     *
     * @param SaveHandler $handler
     */
    public function testSaveHandlers(SaveHandler $handler) : void
    {
        $this->makeSession([], $handler)->start();
        self::assertStringContainsString(
            $handler::class,
            $this->collector->getContents()
        );
    }

    public function testCustomSaveHandlers() : void
    {
        $handler = new class() extends SaveHandler {
            public function open($path, $name) : bool
            {
                return true;
            }

            public function read($id) : string
            {
                return '';
            }

            public function write($id, $data) : bool
            {
                return true;
            }

            public function updateTimestamp($id, $data) : bool
            {
                return true;
            }

            public function close() : bool
            {
                return true;
            }

            public function destroy($id) : bool
            {
                return true;
            }

            public function gc($maxLifetime) : int | false
            {
                return 0;
            }

            protected function lock(string $id) : bool
            {
                return true;
            }

            protected function unlock() : bool
            {
                return true;
            }
        };
        $this->makeSession([], $handler)->start();
        self::assertStringContainsString(
            $handler::class,
            $this->collector->getContents()
        );
    }

    /**
     * @return Generator<array<SaveHandler>>
     */
    public static function saveHandlerProvider() : Generator
    {
        $directory = \sys_get_temp_dir() . '/sessions';
        if (!\is_dir($directory)) {
            \mkdir($directory);
        }
        yield [
            new FilesHandler([
                'directory' => $directory,
            ]),
        ];
        yield [
            new MemcachedHandler([
                'servers' => [
                    [
                        'host' => \getenv('MEMCACHED_HOST'),
                    ],
                    [
                        // @phpstan-ignore-next-line
                        'host' => \gethostbyname(\getenv('MEMCACHED_HOST')),
                    ],
                ],
            ]),
        ];
        yield [
            new RedisHandler([
                'host' => \getenv('REDIS_HOST'),
            ]),
        ];
        $config = [
            'username' => \getenv('DB_USERNAME'),
            'password' => \getenv('DB_PASSWORD'),
            'schema' => \getenv('DB_SCHEMA'),
            'host' => \getenv('DB_HOST'),
            'port' => \getenv('DB_PORT'),
            'table' => \getenv('DB_TABLE'),
        ];
        $database = new Database($config);
        $database->dropTable($config['table'])->ifExists()->run(); // @phpstan-ignore-line
        // @phpstan-ignore-next-line
        $database->createTable($config['table'])
            ->definition(static function (TableDefinition $definition) : void {
                $definition->column('id')->varchar(128)->primaryKey();
                $definition->column('timestamp')->timestamp();
                $definition->column('data')->blob();
                $definition->index('timestamp')->key('timestamp');
            })->run();
        yield [
            new DatabaseHandler($config),
        ];
    }
}
