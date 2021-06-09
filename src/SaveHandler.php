<?php namespace Framework\Session;

/**
 * Class SessionSaveHandler.
 *
 * @see https://www.php.net/manual/en/class.sessionhandler.php
 * @see https://gist.github.com/mindplay-dk/623bdd50c1b4c0553cd3
 * @see https://www.cloudways.com/blog/setup-redis-as-session-handler-php/#sessionlifecycle
 */
abstract class SaveHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	protected array $config;
	protected string $fingerprint;
	protected string | false $lockId = false;
	protected bool $sessionExists = false;
	protected ?string $sessionId;

	/**
	 * SessionSaveHandler constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$this->prepareConfig($config);
	}

	protected function prepareConfig(array $config) : void
	{
		$this->config = $config;
	}

	protected function getLifetime() : int
	{
		return \ini_get('session.cookie_lifetime');
	}

	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function validateId($id) : bool
	{
		$bits = \ini_get('session.sid_bits_per_character') ?: 5;
		$length = \ini_get('session.sid_length') ?: 40;
		$bits_regex = [
			4 => '[0-9a-f]',
			5 => '[0-9a-v]',
			6 => '[0-9a-zA-Z,-]',
		];
		return isset($bits_regex[$bits])
			&& \preg_match('#\A' . $bits_regex[$bits] . '{' . $length . '}\z#', $id);
	}

	abstract public function open($path, $name) : bool;

	abstract public function read($id) : string;

	abstract public function write($id, $data) : bool;

	abstract public function updateTimestamp($id, $data) : bool;

	abstract public function close() : bool;

	abstract public function destroy($id) : bool;

	abstract public function gc($max_lifetime) : bool;

	abstract protected function getLock(string $id) : bool;

	abstract protected function releaseLock() : bool;
}
