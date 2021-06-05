<?php namespace Framework\Session;

/**
 * Class SessionSaveHandler.
 *
 * @see https://www.php.net/manual/en/class.sessionhandler.php
 * @see https://gist.github.com/mindplay-dk/623bdd50c1b4c0553cd3
 */
abstract class SaveHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	protected array $config;
	protected bool $matchIP = false;
	protected bool $matchUA = false;
	protected static ?array $server = null;

	/**
	 * SessionSaveHandler constructor.
	 *
	 * @param array $config
	 * @param bool  $match_ip
	 * @param bool  $match_ua
	 */
	public function __construct(array $config, bool $match_ip = false, bool $match_ua = false)
	{
		$this->prepareConfig($config);
		$this->matchIP = $match_ip;
		$this->matchUA = $match_ua;
	}

	protected function prepareConfig(array $config) : void
	{
		$this->config = $config;
	}

	protected function getLifetime() : int
	{
		return \ini_get('session.cookie_lifetime');
	}

	protected function getServerVar(string $var) : ?string
	{
		static $server;
		if ($server === null) {
			$server['REMOTE_ADDR'] = null;
			if (isset($_SERVER['REMOTE_ADDR'])) {
				$data = \filter_var($_SERVER['REMOTE_ADDR'], \FILTER_SANITIZE_STRING);
				if ($data !== false) {
					$server['REMOTE_ADDR'] = $data;
				}
			}
			$server['HTTP_USER_AGENT'] = null;
			if (isset($_SERVER['HTTP_USER_AGENT'])) {
				$data = \filter_var($_SERVER['HTTP_USER_AGENT'], \FILTER_SANITIZE_STRING);
				if ($data !== false) {
					$server['HTTP_USER_AGENT'] = $data;
				}
			}
		}
		return $server[$var];
	}

	/**
	 * Get the Internet Protocol.
	 *
	 * @return string|null
	 */
	protected function getIP() : ?string
	{
		return $this->getServerVar('REMOTE_ADDR');
	}

	/**
	 * Get the HTTP User-Agent Header.
	 *
	 * @return string|null
	 */
	protected function getUA() : ?string
	{
		return $this->getServerVar('HTTP_USER_AGENT');
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
}
