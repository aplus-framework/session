<?php namespace Tests\Sample;

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

	public function setup()
	{
		$this->session = new Session();
	}

	public function testSetAndGet()
	{
		$this->assertNull($this->session->get('foo'));
		$this->session->set('foo', 123);
		$this->assertEquals(123, $this->session->get('foo'));
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
}
