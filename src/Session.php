<?php namespace Framework\Session;

/**
 * Class Session.
 */
class Session
{
	protected $options = [];

	public function __destruct()
	{
		$this->close();
	}

	public function __get($key)
	{
		return $this->get($key);
	}

	public function __set($key, $value)
	{
		$this->set($key, $value);
	}

	public function __isset($key)
	{
		return isset($_SESSION[$key]);
	}

	public function __unset($key)
	{
		unset($_SESSION[$key]);
	}

	public function start() : bool
	{
		if ($this->isStarted()) {
			throw new \RuntimeException('Session was already started');
		}
		if ( ! \session_start($this->options)) {
			throw new \RuntimeException('Session could not be started.');
		}
		return true;
	}

	public function isStarted() : bool
	{
		return \session_status() === \PHP_SESSION_ACTIVE;
	}

	public function destroy() : bool
	{
		if ($this->isStarted()) {
			$destroyed = \session_destroy();
		}
		unset($_SESSION);
		return $destroyed ?? true;
	}

	public function close() : void
	{
		if ($this->isStarted()) {
			@\session_write_close();
		}
	}

	public function get($key)
	{
		return $_SESSION[$key] ?? null;
	}

	public function getAll() : array
	{
		return $_SESSION;
	}

	public function getMulti(array $keys) : array
	{
		$items = [];
		foreach ($keys as $key) {
			$items[$key] = $this->get($key);
		}
		return $items;
	}

	public function set($key, $value)
	{
		$_SESSION[$key] = $value;
		return $this;
	}

	public function setMulti(array $items)
	{
		foreach ($items as $key => $value) {
			$this->set($key, $value);
		}
		return $this;
	}

	public function remove($key)
	{
		unset($_SESSION[$key]);
		return $this;
	}

	public function removeMulti(array $keys)
	{
		foreach ($keys as $key) {
			$this->remove($key);
		}
		return $this;
	}

	public function removeAll()
	{
		@\session_unset();
		$_SESSION = [];
		return $this;
	}
}
