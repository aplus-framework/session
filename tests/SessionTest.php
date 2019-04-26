<?php namespace Tests\Session;

use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Class SessionTest.
 *
 * @runTestsInSeparateProcesses
 */
class SessionTest extends TestCase
{
	/**
	 * @var Session
	 */
	protected $session;
	/**
	 * @var \Framework\Session\SaveHandler|null
	 */
	protected $handler;

	public function setUp()
	{
		$this->session = new Session(['name' => 'SessionName'], $this->handler);
		$this->session->start();
	}

	protected function tearDown()
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
		$this->assertEquals([], $this->session->getAll());
		$this->session->setMulti(['foo' => 123, 'bar' => 456]);
		$this->assertEquals(
			['foo' => 123, 'bar' => 456, 'baz' => null],
			$this->session->getMulti(['foo', 'bar', 'baz'])
		);
		$this->assertEquals(
			['foo' => 123, 'bar' => 456],
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

	public function testClose()
	{
		$this->assertTrue($this->session->isStarted());
		$this->session->close();
		$this->assertFalse($this->session->isStarted());
	}

	public function testRemove()
	{
		$this->session->foo = 'foo';
		$this->session->bar = 'bar';
		$this->session->baz = 'baz';
		$this->assertEquals(
			['foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'],
			$this->session->getAll()
		);
		$this->session->removeMulti(['foo', 'baz']);
		$this->assertEquals(
			['bar' => 'bar'],
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
}
