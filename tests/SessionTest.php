<?php namespace Tests\Session;

use Framework\Session\SaveHandler;
use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Class SessionTest.
 *
 * @runTestsInSeparateProcesses
 */
class SessionTest extends TestCase
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

	public function testCustomOptions()
	{
		$this->assertEquals('SessionName', \session_name());
	}

	public function testSetAndGet()
	{
		$this->assertNull($this->session->get('foo'));
		$this->session->set('foo', 123);
		$this->assertEquals(123, $this->session->get('foo'));
	}

	public function testMultiAndAll()
	{
		$this->assertEquals(
			['foo' => null, 'bar' => null, 'baz' => null],
			$this->session->getMulti(['foo', 'bar', 'baz'])
		);
		$this->assertEquals(['$' => $this->session->get('$')], $this->session->getAll());
		$this->session->setMulti(['foo' => 123, 'bar' => 456]);
		$this->assertEquals(
			['foo' => 123, 'bar' => 456, 'baz' => null],
			$this->session->getMulti(['foo', 'bar', 'baz'])
		);
		$this->assertEquals(
			['foo' => 123, 'bar' => 456, '$' => $this->session->get('$')],
			$this->session->getAll()
		);
	}

	public function testMagicSetAndGet()
	{
		$this->assertNull($this->session->foo);
		$this->session->foo = 123;
		$this->assertEquals(123, $this->session->foo);
	}

	public function testMagicIssetAndUnset()
	{
		$this->assertFalse(isset($this->session->foo));
		$this->session->foo = 123;
		$this->assertTrue(isset($this->session->foo));
		unset($this->session->foo);
		$this->assertFalse(isset($this->session->foo));
	}

	public function testStop()
	{
		$this->assertTrue($this->session->isStarted());
		$this->session->stop();
		$this->assertFalse($this->session->isStarted());
	}

	public function testRemove()
	{
		$this->session->foo = 'foo';
		$this->session->bar = 'bar';
		$this->session->baz = 'baz';
		$this->assertEquals(
			['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz', '$' => $this->session->get('$')],
			$this->session->getAll()
		);
		$this->session->removeMulti(['foo', 'baz']);
		$this->assertEquals(
			['bar' => 'bar', '$' => $this->session->get('$')],
			$this->session->getAll()
		);
		$this->session->removeAll();
		$this->assertEquals([], $this->session->getAll());
	}

	public function testAlreadyStarted()
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('Session was already started');
		$this->session->start();
	}

	public function testFlash()
	{
		$this->session->setFlash('foo', 'Foo');
		$this->session->setFlash('bar', 'Bar');
		$this->assertEquals('Foo', $this->session->getFlash('foo'));
		$this->assertEquals('Bar', $this->session->getFlash('bar'));
		$this->session->removeFlash('foo');
		$this->assertNull($this->session->getFlash('foo'));
		$this->assertEquals('Bar', $this->session->getFlash('bar'));
		$this->session->stop();
		$this->session->start();
		$this->assertEquals('Bar', $this->session->getFlash('bar'));
		$this->session->stop();
		$this->session->start();
		$this->assertNull($this->session->getFlash('bar'));
	}

	public function testSetAndGetTemp()
	{
		$this->session->setTemp('foo', 'Foo', 1);
		$this->session->setTemp('bar', 'Bar', 2);
		$this->assertEquals('Foo', $this->session->getTemp('foo'));
		$this->assertEquals('Bar', $this->session->getTemp('bar'));
		\sleep(1);
		$this->assertNull($this->session->getTemp('foo'));
		$this->assertEquals('Bar', $this->session->getTemp('bar'));
		\sleep(1);
		$this->assertNull($this->session->getTemp('bar'));
	}

	public function testRemoveTemp()
	{
		$this->session->setTemp('foo', 'Foo');
		$this->assertEquals('Foo', $this->session->getTemp('foo'));
		$this->session->removeTemp('foo');
		$this->assertNull($this->session->getTemp('foo'));
	}

	public function testAutoClearTemp()
	{
		$this->session->setTemp('foo', 'Foo', 1);
		$this->session->stop();
		$this->session->start();
		$this->assertEquals('Foo', $this->session->getTemp('foo'));
		$this->session->stop();
		\sleep(2);
		$this->session->start();
		$this->assertNull($this->session->getTemp('foo'));
	}

	public function testResetFlash()
	{
		$this->session->setFlash('foo', 'bar');
		$this->assertEquals('bar', $this->session->getFlash('foo'));
		$this->session->stop();
		$this->session->start();
		$this->assertEquals('bar', $this->session->getFlash('foo'));
		$this->session->setFlash('foo', 'bazz');
		$this->assertEquals('bazz', $this->session->getFlash('foo'));
		$this->session->reset();
		$this->assertEquals('bar', $this->session->getFlash('foo'));
		$this->session->stop();
		$this->session->start();
		$this->assertEquals('bar', $this->session->getFlash('foo'));
		$this->session->stop();
		$this->session->start();
		$this->assertNull($this->session->getFlash('foo'));
	}
}
