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

	public function testCustomOptions() : void
	{
		self::assertSame('SessionName', \session_name());
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
		self::assertSame(['$' => $this->session->get('$')], $this->session->getAll());
		$this->session->setMulti(['foo' => 123, 'bar' => 456]);
		self::assertSame(
			['foo' => 123, 'bar' => 456, 'baz' => null],
			$this->session->getMulti(['foo', 'bar', 'baz'])
		);
		self::assertSame(
			['$' => $this->session->get('$'), 'foo' => 123, 'bar' => 456],
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
		self::assertTrue($this->session->isStarted());
		$this->session->stop();
		self::assertFalse($this->session->isStarted());
	}

	public function testRemove() : void
	{
		$this->session->foo = 'foo';
		$this->session->bar = 'bar';
		$this->session->baz = 'baz';
		self::assertSame(
			['$' => $this->session->get('$'), 'foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'],
			$this->session->getAll()
		);
		$this->session->removeMulti(['foo', 'baz']);
		self::assertSame(
			['$' => $this->session->get('$'), 'bar' => 'bar'],
			$this->session->getAll()
		);
		$this->session->removeAll();
		self::assertSame([], $this->session->getAll());
	}

	public function testAlreadyStarted() : void
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('Session was already started');
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
}
