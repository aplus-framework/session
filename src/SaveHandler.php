<?php namespace Framework\Session;

use Framework\Log\Logger;

/**
 * Class SessionSaveHandler.
 *
 * @see https://www.php.net/manual/en/class.sessionhandler.php
 * @see https://gist.github.com/mindplay-dk/623bdd50c1b4c0553cd3
 * @see https://www.cloudways.com/blog/setup-redis-as-session-handler-php/#sessionlifecycle
 */
abstract class SaveHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	/**
	 * @var array<string,mixed>
	 */
	protected array $config;
	/**
	 * The current data fingerprint.
	 *
	 * Normally the md5 hash of the data.
	 *
	 * @var string
	 */
	protected string $fingerprint;
	protected string | false $lockId = false;
	protected bool $sessionExists = false;
	protected ?string $sessionId;
	protected ?Logger $logger;

	/**
	 * SessionSaveHandler constructor.
	 *
	 * @param array<string,mixed> $config
	 * @param Logger|null $logger
	 */
	public function __construct(array $config = [], Logger $logger = null)
	{
		$this->prepareConfig($config);
		$this->logger = $logger;
	}

	/**
	 * @param array<string,mixed> $config
	 *
	 * @codeCoverageIgnore
	 */
	protected function prepareConfig(array $config) : void
	{
		$this->config = $config;
	}

	protected function log(string $message, int $level = Logger::ERROR) : void
	{
		if ($this->logger) {
			$this->logger->log($level, $message);
		}
	}

	/**
	 * Set the data fingerprint.
	 *
	 * @param string $data The data to set the new fingerprint
	 */
	protected function setFingerprint(string $data) : void
	{
		$this->fingerprint = $this->makeFingerprint($data);
	}

	private function makeFingerprint(string $data) : string
	{
		return \md5($data);
	}

	/**
	 * Tells if the data has the same current fingerprint.
	 *
	 * @param string $data The data to compare
	 *
	 * @return bool True if the fingerprints are the same, otherwise false
	 */
	protected function hasSameFingerprint(string $data) : bool
	{
		return $this->fingerprint === $this->makeFingerprint($data);
	}

	protected function getMaxlifetime() : int
	{
		return $this->config['maxlifetime'] ?? \ini_get('session.gc_maxlifetime');
	}

	/**
	 * Get the remote IP address.
	 *
	 * @return string
	 */
	protected function getIP() : string
	{
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	/**
	 * Get the HTTP User-Agent.
	 *
	 * @return string
	 */
	protected function getUA() : string
	{
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
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

	abstract protected function lock(string $id) : bool;

	abstract protected function unlock() : bool;
}
