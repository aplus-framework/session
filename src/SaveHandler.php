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
	/**
	 * @var bool
	 */
	protected $matchIP = false;
	/**
	 * @var bool
	 */
	protected $matchUA = false;

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

	/**
	 * @return int
	 *
	 * @codeCoverageIgnore
	 */
	protected function getLifetime() : int
	{
		return \ini_get('session.cookie_lifetime');
	}

	protected function getServerVar(string $var) : ?string
	{
		static $server;
		if ($server === null) {
			$server = \filter_input_array(\INPUT_SERVER);
		}
		return $server[$var] ?? null;
	}

	protected function getIP() : string
	{
		return $this->getServerVar('REMOTE_ADDR');
	}

	protected function getUA() : string
	{
		return $this->getServerVar('HTTP_USER_AGENT') ?? '';
	}

	/**
	 * @param string $session_id
	 *
	 * @return bool
	 * @codeCoverageIgnore
	 */
	public function validateId($session_id) : bool
	{
		$bits_per_character = \ini_get('session.sid_bits_per_character') ?: 4;
		$length = \ini_get('session.sid_length') ?: 40;
		switch ($bits_per_character) {
			case 4:
				$regex = '[0-9a-f]';
				break;
			case 5:
				$regex = '[0-9a-v]';
				break;
			case 6:
				$regex = '[0-9a-zA-Z,-]';
				break;
			default:
				return false;
		}
		$regex .= '{' . $length . '}';
		return (bool) \preg_match('#\A' . $regex . '\z#', $session_id);
	}

	abstract public function open($save_path, $name) : bool;

	abstract public function read($session_id) : string;

	abstract public function write($session_id, $session_data) : bool;

	abstract public function updateTimestamp($session_id, $session_data) : bool;

	abstract public function close() : bool;

	abstract public function destroy($session_id) : bool;

	abstract public function gc($maxlifetime) : bool;
}
