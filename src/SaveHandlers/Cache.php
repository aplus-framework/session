<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

/**
 * Class Cache.
 *
 * @property \Framework\Cache\Cache $handler
 */
class Cache extends SaveHandler
{
	protected function getKey($session_id)
	{
		if ($this->matchIP) {
			$session_id .= '-' . $this->getIP();
		}
		if ($this->matchUA) {
			$session_id .= '-' . $this->getUA();
		}
		if ($this->matchIP || $this->matchUA) {
			$session_id = \md5($session_id);
		}
		return $session_id;
	}

	public function open($save_path, $name) : bool
	{
		return true;
	}

	public function read($session_id) : string
	{
		return (string) $this->handler->get($this->getKey($session_id));
	}

	public function write($session_id, $session_data) : bool
	{
		return $this->handler->set(
			$this->getKey($session_id),
			$session_data,
			$this->getLifetime()
		);
	}

	public function updateTimestamp($session_id, $session_data) : bool
	{
		return $this->handler->set(
			$this->getKey($session_id),
			$session_data,
			$this->getLifetime()
		);
	}

	public function close() : bool
	{
		return true;
	}

	public function destroy($session_id) : bool
	{
		return $this->handler->delete($this->getKey($session_id));
	}

	public function gc($maxlifetime) : bool
	{
		if (\method_exists($this->handler, 'gc')) {
			return $this->handler->gc();
		}
		return true;
	}
}
