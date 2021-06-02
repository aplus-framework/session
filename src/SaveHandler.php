<?php namespace Framework\Session;

/**
 * Class SessionSaveHandler.
 *
 * @todo    Allow enable/disable locks?
 */
abstract class SaveHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	/**
	 * @var mixed
	 */
	protected $handler;
	protected bool $matchIP = false;
	protected bool $matchUA = false;

	/**
	 * SessionSaveHandler constructor.
	 *
	 * @param mixed $handler
	 * @param bool  $match_ip
	 * @param bool  $match_ua
	 */
	public function __construct($handler, bool $match_ip = false, bool $match_ua = false)
	{
		$this->handler = $handler;
		$this->matchIP = $match_ip;
		$this->matchUA = $match_ua;
	}

	protected function getLifetime() : int
	{
		return \ini_get('session.cookie_lifetime');
	}

	protected function getServerVar(string $var) : ?string
	{
		static $server;
		if ($server === null) {
			$server = [
				'REMOTE_ADDR' => \filter_input(
					\INPUT_SERVER,
					'REMOTE_ADDR',
					\FILTER_SANITIZE_STRING
				),
				'HTTP_USER_AGENT' => \filter_input(
					\INPUT_SERVER,
					'HTTP_USER_AGENT',
					\FILTER_SANITIZE_STRING
				),
			];
		}
		return $server[$var] ?? null;
	}

	public function getIP() : ?string
	{
		return $this->getServerVar('REMOTE_ADDR');
	}

	public function getUA() : ?string
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
			? (bool) \preg_match('#\A' . $bits_regex[$bits] . '{' . $length . '}\z#', $id)
			: false;
	}

	abstract public function open($path, $name) : bool;

	abstract public function read($id) : string;

	abstract public function write($id, $data) : bool;

	abstract public function updateTimestamp($id, $data) : bool;

	abstract public function close() : bool;

	abstract public function destroy($id) : bool;

	abstract public function gc($max_lifetime) : bool;
}
