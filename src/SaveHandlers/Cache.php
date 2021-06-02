<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

/**
 * Class Cache.
 *
 * @property \Framework\Cache\Cache $handler
 */
class Cache extends SaveHandler
{
	protected function getKey($id) : string
	{
		if ($this->matchIP) {
			$id .= '-' . $this->getIP();
		}
		if ($this->matchUA) {
			$id .= '-' . $this->getUA();
		}
		if ($this->matchIP || $this->matchUA) {
			$id = \md5($id);
		}
		return $id;
	}

	public function open($path, $name) : bool
	{
		return true;
	}

	public function read($id) : string
	{
		return (string) $this->handler->get($this->getKey($id));
	}

	public function write($id, $data) : bool
	{
		return $this->handler->set(
			$this->getKey($id),
			$data,
			$this->getLifetime()
		);
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->handler->set(
			$this->getKey($id),
			$data,
			$this->getLifetime()
		);
	}

	public function close() : bool
	{
		return true;
	}

	public function destroy($id) : bool
	{
		return $this->handler->delete($this->getKey($id));
	}

	public function gc($max_lifetime) : bool
	{
		return \method_exists($this->handler, 'gc') ? $this->handler->gc() : true;
	}
}
