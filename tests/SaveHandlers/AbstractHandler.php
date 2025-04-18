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

use Framework\Log\Logger;
use Framework\Log\Loggers\MultiFileLogger;
use Tests\Session\TestCase;

/**
 * Class AbstractHandler.
 *
 * @runTestsInSeparateProcesses
 */
abstract class AbstractHandler extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];
    protected string $handlerClass;
    protected Logger $logger;

    public function setUp() : void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';
        $this->logger = new MultiFileLogger('/tmp');
        $this->handler = new $this->handlerClass($this->config, $this->logger); // @phpstan-ignore-line
        parent::setUp();
    }

    /**
     * @param array<string,mixed> $config
     */
    protected function replaceConfig(array $config) : void
    {
        $this->config = \array_replace_recursive($this->config, $config);
    }

    /**
     * @see https://wiki.php.net/rfc/deprecations_php_8_4#sessionsid_length_and_sessionsid_bits_per_character
     */
    public function testValidateId() : void
    {
        // Ids with PHP lower than 8.4:
        $id6 = '62my7tSXcbIrOZ-WHsEXhpwUoG,afmBQNGaSBkFN';
        $id5 = 'iimuf8lvdectatt5jtkve15831funl8rg5cg6okp';
        $id4 = '96aa2c863140e0e714a603cf44b0afc9a0632592';
        $this->session->stop();
        /*
         * Since PHP 8.4 session.sid_bits_per_character and session.sid_length
         * are deprecated.
         *
         * - The default value for session.sid_bits_per_character is 4 (0-9, a-f).
         * - The default value for session.sid_length is 32 character.
         */
        if (\PHP_VERSION_ID >= 80400) {
            self::assertFalse($this->handler->validateId($id6));
            self::assertFalse($this->handler->validateId($id5));
            self::assertFalse($this->handler->validateId($id4));
            $idPhp84 = '96aa2c863140e0e714a603cf44b0afc9';
            self::assertTrue($this->handler->validateId($idPhp84));
            return;
        }
        \ini_set('session.sid_bits_per_character', '6');
        \ini_set('session.sid_length', '40');
        self::assertTrue($this->handler->validateId($id6));
        self::assertTrue($this->handler->validateId($id5));
        self::assertTrue($this->handler->validateId($id4));
        \ini_set('session.sid_bits_per_character', '5');
        self::assertFalse($this->handler->validateId($id6));
        self::assertTrue($this->handler->validateId($id5));
        self::assertTrue($this->handler->validateId($id4));
        \ini_set('session.sid_bits_per_character', '4');
        self::assertFalse($this->handler->validateId($id6));
        self::assertFalse($this->handler->validateId($id5));
        self::assertTrue($this->handler->validateId($id4));
    }

    public function testGC() : void
    {
        $this->session->stop();
        $this->session->start([
            'gc_maxlifetime' => 1,
        ]);
        $this->session->foo = 'bar';
        self::assertSame($this->session->foo, 'bar');
        $this->session->stop();
        \sleep(2);
        self::assertIsInt($this->handler->gc(0));
        $this->session->start();
        self::assertNull($this->session->foo);
    }

    public function testRegenerate() : void
    {
        self::assertTrue($this->session->regenerateId());
        self::assertTrue($this->session->regenerateId(true));
    }

    public function testReset() : void
    {
        self::assertTrue($this->session->reset());
    }
}
