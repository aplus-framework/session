<?php namespace Framework\Session;

/**
 * Class Session.
 */
class Session
{
	protected $options = [];

	public function __construct(array $options = [], SaveHandler $handler = null)
	{
		$this->setOptions($options);
		if ($handler) {
			\session_set_save_handler($handler);
		}
	}

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
		return $this->has($key);
	}

	public function __unset($key)
	{
		return $this->remove($key);
	}

	/**
	 * @see http://php.net/manual/en/session.security.ini.php
	 *
	 * @var array $custom
	 */
	protected function setOptions(array $custom)
	{
		$default = [
			'name' => 'session_id',
			'serialize_handler' => \ini_get('session.serialize_handler') === 'php'
				? 'php_serialize'
				: \ini_get('session.serialize_handler'),
			'sid_bits_per_character' => 6,
			'sid_length' => 48,
			'cookie_domain' => '',
			'cookie_httponly' => 1,
			'cookie_lifetime' => 7200,
			'cookie_path' => '/',
			'cookie_samesite' => 'Strict',
			'cookie_secure' => (\filter_input(\INPUT_SERVER, 'REQUEST_SCHEME') === 'https'
				|| \filter_input(\INPUT_SERVER, 'HTTPS') === 'on')
				? 1 : 0,
			'referer_check' => '',
			'use_cookies' => 1,
			'use_only_cookies' => 1,
			'use_strict_mode' => 1,
			'use_trans_sid' => 0,
		];
		if ($custom) {
			$default = \array_replace($default, $custom);
		}
		$this->options = $default;
	}

	public function start() : bool
	{
		if ($this->isStarted()) {
			throw new \LogicException('Session was already started');
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

	public function close() : bool
	{
		if ($this->isStarted()) {
			$closed = \session_write_close();
		}
		return $closed ?? true;
	}

	public function has(string $key) : bool
	{
		return isset($_SESSION[$key]);
	}

	public function get(string $key)
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

	public function set(string $key, $value)
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

	public function remove(string $key)
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
