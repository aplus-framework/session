<?php
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\Session;

use Framework\Session\SaveHandler;
use Framework\Session\Session;

/**
 * Class TestCase.
 *
 * @runTestsInSeparateProcesses
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected ?Session $session = null;
    protected ?SaveHandler $handler = null;

    public function setUp() : void
    {
        $this->session = new Session(['name' => 'SessionName'], $this->handler);
        $this->session->start();
    }

    protected function tearDown() : void
    {
        $this->session->destroy();
        $this->session = null;
    }

    public function testCustomOptions() : void
    {
        self::assertSame('SessionName', \session_name());
    }

    public function testActivate() : void
    {
        self::assertTrue($this->session->isActive());
        self::assertTrue($this->session->activate());
        $this->session->stop();
        self::assertFalse($this->session->isActive());
        self::assertTrue($this->session->activate());
        self::assertTrue($this->session->isActive());
    }

    public function testSetAndGet() : void
    {
        self::assertNull($this->session->get('foo'));
        $this->session->set('foo', 123);
        self::assertSame(123, $this->session->get('foo'));
    }

    public function testMultiAndAll() : void
    {
        self::assertSame(
            ['foo' => null, 'bar' => null, 'baz' => null],
            $this->session->getMulti(['foo', 'bar', 'baz'])
        );
        self::assertSame([], $this->session->getAll());
        $this->session->setMulti(['foo' => 123, 'bar' => 456]);
        self::assertSame(
            ['foo' => 123, 'bar' => 456, 'baz' => null],
            $this->session->getMulti(['foo', 'bar', 'baz'])
        );
        self::assertSame(
            ['foo' => 123, 'bar' => 456],
            $this->session->getAll()
        );
    }

    public function testMagicSetAndGet() : void
    {
        self::assertNull($this->session->foo);
        $this->session->foo = 123;
        self::assertSame(123, $this->session->foo);
    }

    public function testMagicIssetAndUnset() : void
    {
        self::assertFalse(isset($this->session->foo));
        $this->session->foo = 123;
        self::assertTrue(isset($this->session->foo));
        unset($this->session->foo);
        self::assertFalse(isset($this->session->foo));
    }

    public function testStop() : void
    {
        self::assertTrue($this->session->isActive());
        $this->session->stop();
        self::assertFalse($this->session->isActive());
    }

    public function testAbort() : void
    {
        self::assertTrue($this->session->isActive());
        $this->session->foo = 1;
        self::assertSame(1, $this->session->foo);
        $this->session->stop();
        $this->session->start();
        self::assertSame(1, $this->session->foo);
        $this->session->foo = 3;
        self::assertSame(3, $this->session->foo);
        self::assertTrue($this->session->abort());
        self::assertFalse($this->session->isActive());
        $this->session->start();
        self::assertSame(1, $this->session->foo);
    }

    public function testRemove() : void
    {
        $this->session->foo = 'foo';
        $this->session->bar = 'bar';
        $this->session->baz = 'baz';
        self::assertSame(
            ['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'],
            $this->session->getAll()
        );
        $this->session->removeMulti(['foo', 'baz']);
        self::assertSame(
            ['bar' => 'bar'],
            $this->session->getAll()
        );
        $this->session->removeAll();
        self::assertSame([], $this->session->getAll());
    }

    public function testAlreadyActive() : void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Session was already active');
        $this->session->start();
    }

    public function testFlash() : void
    {
        $this->session->setFlash('foo', 'Foo');
        $this->session->setFlash('bar', 'Bar');
        self::assertSame('Foo', $this->session->getFlash('foo'));
        self::assertSame('Bar', $this->session->getFlash('bar'));
        $this->session->removeFlash('foo');
        self::assertNull($this->session->getFlash('foo'));
        self::assertSame('Bar', $this->session->getFlash('bar'));
        $this->session->stop();
        $this->session->start();
        self::assertSame('Bar', $this->session->getFlash('bar'));
        $this->session->stop();
        $this->session->start();
        self::assertNull($this->session->getFlash('bar'));
    }

    public function testSetAndGetTemp() : void
    {
        $this->session->setTemp('foo', 'Foo', 1);
        $this->session->setTemp('bar', 'Bar', 2);
        self::assertSame('Foo', $this->session->getTemp('foo'));
        self::assertSame('Bar', $this->session->getTemp('bar'));
        \sleep(1);
        self::assertNull($this->session->getTemp('foo'));
        self::assertSame('Bar', $this->session->getTemp('bar'));
        \sleep(1);
        self::assertNull($this->session->getTemp('bar'));
    }

    public function testRemoveTemp() : void
    {
        $this->session->setTemp('foo', 'Foo');
        self::assertSame('Foo', $this->session->getTemp('foo'));
        $this->session->removeTemp('foo');
        self::assertNull($this->session->getTemp('foo'));
    }

    public function testAutoClearTemp() : void
    {
        $this->session->setTemp('foo', 'Foo', 2);
        $this->session->stop();
        $this->session->start();
        self::assertSame('Foo', $this->session->getTemp('foo'));
        $this->session->stop();
        \sleep(3);
        $this->session->start();
        self::assertNull($this->session->getTemp('foo'));
    }

    public function testResetFlash() : void
    {
        $this->session->setFlash('foo', 'bar');
        self::assertSame('bar', $this->session->getFlash('foo'));
        $this->session->stop();
        $this->session->start();
        self::assertSame('bar', $this->session->getFlash('foo'));
        $this->session->setFlash('foo', 'bazz');
        self::assertSame('bazz', $this->session->getFlash('foo'));
        $this->session->reset();
        self::assertSame('bar', $this->session->getFlash('foo'));
        $this->session->stop();
        $this->session->start();
        self::assertSame('bar', $this->session->getFlash('foo'));
        $this->session->stop();
        $this->session->start();
        self::assertNull($this->session->getFlash('foo'));
    }

    public function testId() : void
    {
        $this->session->stop();
        $old_id = \session_id();
        self::assertNotEmpty($old_id);
        self::assertSame($old_id, $this->session->id());
        $new_id = 'abc';
        self::assertSame($old_id, $this->session->id($new_id));
        self::assertSame($new_id, \session_id());
        self::assertSame($new_id, $this->session->id());
        $this->session->start();
        self::assertNotSame($new_id, $this->session->id());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Session ID cannot be changed when a session is active');
        $this->session->id('foo');
    }

    public function testGc() : void
    {
        self::assertIsInt($this->session->gc());
    }

    public function testAutoRegenerate() : void
    {
        $this->session->set('foo', 'bar');
        self::assertSame('bar', $this->session->get('foo'));
        $id = (string) $this->session->id();
        $this->session->stop();
        $this->session = new Session([], $this->handler);
        $this->session->id($id);
        $this->session->start();
        self::assertSame('bar', $this->session->get('foo'));
        $this->session->stop();
        $this->session = new Session(['auto_regenerate_maxlifetime' => 2], $this->handler);
        $this->session->id($id);
        $this->session->start();
        self::assertSame('bar', $this->session->get('foo'));
        $this->session->stop();
        \sleep(2);
        $this->session->id($id);
        $this->session->start();
        self::assertNull($this->session->get('foo'));
    }

    public function testDestroyCookie() : void
    {
        self::assertTrue($this->session->destroyCookie());
    }

    /**
     * By default, a Set-Cookie header is set when the session starts.
     *
     * @see TestCase::testSetCookiePermanent()
     */
    public function testSetOneCookie() : void
    {
        $this->session->stop();
        $headers = xdebug_get_headers();
        $count = 0;
        foreach ($headers as $header) {
            if (\str_starts_with($header, 'Set-Cookie: SessionName=')) {
                $count++;
            }
        }
        self::assertSame(1, $count);
    }

    /**
     * The first time the session is started with the set_cookie_permanent
     * option, two identical Set-Cookie headers are set.
     *
     * In future requests the Set-Cookie header is set only once if the request
     * contains the Cookie header with the session name and a valid value (but
     * this could not be tested).
     *
     * @see TestCase::testSetOneCookie()
     */
    public function testSetCookiePermanent() : void
    {
        $this->session->stop();
        $this->session = new Session([
            'name' => 'sess_id',
            'set_cookie_permanent' => 1,
        ], $this->handler);
        $this->session->start();
        $headers = xdebug_get_headers();
        $count = 0;
        foreach ($headers as $header) {
            if (\str_starts_with($header, 'Set-Cookie: sess_id=')) {
                $count++;
            }
        }
        self::assertSame(2, $count);
    }
}
