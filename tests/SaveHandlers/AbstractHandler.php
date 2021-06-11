<?php namespace Tests\Session\SaveHandlers;

use Tests\Session\SessionTest;

/**
 * Class AbstractHandler.
 *
 * @runTestsInSeparateProcesses
 */
abstract class AbstractHandler extends SessionTest
{
	/**
	 * @var array<string, mixed>
	 */
	protected array $config = [];

	public function testValidateId() : void
	{
		$id6 = '62my7tSXcbIrOZ-WHsEXhpwUoG,afmBQNGaSBkFN';
		$id5 = 'iimuf8lvdectatt5jtkve15831funl8rg5cg6okp';
		$id4 = '96aa2c863140e0e714a603cf44b0afc9a0632592';
		$this->session->stop();
		\ini_set('session.sid_bits_per_character', '6');
		\ini_set('session.sid_length', '40');
		$this->assertTrue($this->handler->validateId($id6));
		$this->assertTrue($this->handler->validateId($id5));
		$this->assertTrue($this->handler->validateId($id4));
		\ini_set('session.sid_bits_per_character', '5');
		$this->assertFalse($this->handler->validateId($id6));
		$this->assertTrue($this->handler->validateId($id5));
		$this->assertTrue($this->handler->validateId($id4));
		\ini_set('session.sid_bits_per_character', '4');
		$this->assertFalse($this->handler->validateId($id6));
		$this->assertFalse($this->handler->validateId($id5));
		$this->assertTrue($this->handler->validateId($id4));
	}

	public function testGC() : void
	{
		$this->session->stop();
		$this->session->start([
			'gc_maxlifetime' => 1,
		]);
		$this->session->foo = 'bar';
		$this->assertEquals($this->session->foo, 'bar');
		$this->session->stop();
		\sleep(2);
		$this->assertTrue($this->handler->gc(0));
		$this->session->start();
		$this->assertNull($this->session->foo);
	}

	public function testRegenerate() : void
	{
		$this->assertTrue($this->session->regenerate());
		$this->assertTrue($this->session->regenerate(true));
	}

	public function testReset() : void
	{
		$this->assertTrue($this->session->reset());
	}
}
