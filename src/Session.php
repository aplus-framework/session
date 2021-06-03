<?php namespace Framework\Session;

use LogicException;
use RuntimeException;

/**
 * Class Session.
 */
class Session
{
	/**
	 * @var array|mixed[]
	 */
	protected array $options = [];

	/**
	 * Session constructor.
	 *
	 * @param array|mixed[]    $options
	 * @param SaveHandler|null $handler
	 */
	public function __construct(array $options = [], SaveHandler $handler = null)
	{
		$this->setOptions($options);
		if ($handler) {
			\session_set_save_handler($handler);
		}
	}

	public function __destruct()
	{
		$this->stop();
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
	 * @param array|mixed[] $custom
	 */
	protected function setOptions(array $custom) : void
	{
		$serializer = \ini_get('session.serialize_handler');
		$serializer = $serializer === 'php' ? 'php_serialize' : $serializer;
		$secure = \filter_input(\INPUT_SERVER, 'REQUEST_SCHEME') === 'https'
			|| \filter_input(\INPUT_SERVER, 'HTTPS') === 'on';
		$default = [
			'name' => 'session_id',
			'serialize_handler' => $serializer,
			'sid_bits_per_character' => 6,
			'sid_length' => 48,
			'cookie_domain' => '',
			'cookie_httponly' => 1,
			'cookie_lifetime' => 7200,
			'cookie_path' => '/',
			'cookie_samesite' => 'Strict',
			'cookie_secure' => $secure,
			'referer_check' => '',
			'regenerate_id' => 64800,
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

	/**
	 * @param array|mixed[] $custom
	 *
	 * @return array|mixed[]
	 */
	protected function getOptions(array $custom = []) : array
	{
		$options = $this->options;
		if ($custom) {
			$options = \array_replace($this->options, $custom);
		}
		unset($options['regenerate_id']);
		return $options;
	}

	/**
	 * @param array|mixed[] $custom_options
	 *
	 * @return bool
	 */
	public function start(array $custom_options = []) : bool
	{
		if ($this->isStarted()) {
			throw new LogicException('Session was already started');
		}
		if ( ! \session_start($this->getOptions($custom_options))) {
			throw new RuntimeException('Session could not be started.');
		}
		$time = \time();
		$this->autoRegenerate($time);
		$this->clearTemp($time);
		$this->clearFlash();
		return true;
	}

	protected function autoRegenerate(int $time) : void
	{
		if (empty($_SESSION['$']['regenerated_at'])
			|| $_SESSION['$']['regenerated_at'] < $time - $this->options['regenerate_id']
		) {
			$this->regenerate(false);
		}
	}

	protected function clearFlash() : void
	{
		unset($_SESSION['$']['flash']['old']);
		if (isset($_SESSION['$']['flash']['new'])) {
			foreach ($_SESSION['$']['flash']['new'] as $key => $value) {
				$_SESSION['$']['flash']['old'][$key] = $value;
			}
		}
		unset($_SESSION['$']['flash']['new']);
		if (empty($_SESSION['$']['flash'])) {
			unset($_SESSION['$']['flash']);
		}
	}

	protected function clearTemp(int $time) : void
	{
		if (isset($_SESSION['$']['temp'])) {
			foreach ($_SESSION['$']['temp'] as $key => $value) {
				if ($value['ttl'] < $time) {
					unset($_SESSION['$']['temp'][$key]);
				}
			}
		}
		if (empty($_SESSION['$']['temp'])) {
			unset($_SESSION['$']['temp']);
		}
	}

	public function isStarted() : bool
	{
		return \session_status() === \PHP_SESSION_ACTIVE;
	}

	/**
	 * Destroys all data registered to a session.
	 *
	 * @return bool true on success or false on failure
	 */
	public function destroy() : bool
	{
		if ($this->isStarted()) {
			$destroyed = \session_destroy();
		}
		unset($_SESSION);
		return $destroyed ?? true;
	}

	/**
	 * Write session data and end session.
	 *
	 * @return bool returns true on success or false on failure
	 */
	public function stop() : bool
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

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get(string $key) : mixed
	{
		return $_SESSION[$key] ?? null;
	}

	/**
	 * @return array|mixed[]
	 */
	public function getAll() : array
	{
		return $_SESSION;
	}

	/**
	 * @param array|string[] $keys
	 *
	 * @return array|mixed[]
	 */
	public function getMulti(array $keys) : array
	{
		$items = [];
		foreach ($keys as $key) {
			$items[$key] = $this->get($key);
		}
		return $items;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function set(string $key, mixed $value)
	{
		$_SESSION[$key] = $value;
		return $this;
	}

	/**
	 * @param array|mixed[] $items
	 *
	 * @return $this
	 */
	public function setMulti(array $items)
	{
		foreach ($items as $key => $value) {
			$this->set($key, $value);
		}
		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return $this
	 */
	public function remove(string $key)
	{
		unset($_SESSION[$key]);
		return $this;
	}

	/**
	 * @param array|string[] $keys
	 *
	 * @return $this
	 */
	public function removeMulti(array $keys)
	{
		foreach ($keys as $key) {
			$this->remove($key);
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function removeAll()
	{
		@\session_unset();
		$_SESSION = [];
		return $this;
	}

	/**
	 * Update the current session id with a newly generated one.
	 *
	 * @param bool $delete_old_session Whether to delete the old associated session file or not
	 *
	 * @return bool
	 */
	public function regenerate(bool $delete_old_session = false) : bool
	{
		$regenerated = \session_regenerate_id($delete_old_session);
		$_SESSION['$']['regenerated_at'] = \time();
		return $regenerated;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getFlash(string $key) : mixed
	{
		return $_SESSION['$']['flash']['new'][$key]
			?? $_SESSION['$']['flash']['old'][$key]
			?? null;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function setFlash(string $key, mixed $value)
	{
		$_SESSION['$']['flash']['new'][$key] = $value;
		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return $this
	 */
	public function removeFlash(string $key)
	{
		unset(
			$_SESSION['$']['flash']['old'][$key],
			$_SESSION['$']['flash']['new'][$key]
		);
		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getTemp(string $key) : mixed
	{
		if (isset($_SESSION['$']['temp'][$key])) {
			if ($_SESSION['$']['temp'][$key]['ttl'] > \time()) {
				return $_SESSION['$']['temp'][$key]['data'];
			}
			unset($_SESSION['$']['temp'][$key]);
		}
		return null;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $ttl
	 *
	 * @return $this
	 */
	public function setTemp(string $key, mixed $value, int $ttl = 60)
	{
		$_SESSION['$']['temp'][$key] = [
			'ttl' => \time() + $ttl,
			'data' => $value,
		];
		return $this;
	}

	/**
	 * @param string $key
	 *
	 * @return $this
	 */
	public function removeTemp(string $key)
	{
		unset($_SESSION['$']['temp'][$key]);
		return $this;
	}
}
