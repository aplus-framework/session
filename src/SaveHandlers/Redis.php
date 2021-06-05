<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

class Redis extends SaveHandler
{
	protected ?\Redis $redis;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => 'session',
			'host' => '127.0.0.1',
			'port' => 6379,
			'timeout' => 0.0,
		], $config);
	}

	protected function getKey(string $id) : string
	{
		return $this->config['prefix'] . ':' . $id;
	}

	public function open($path, $name) : bool
	{
		if (isset($this->redis)) {
			return true;
		}
		$this->redis = new \Redis();
		return $this->redis->connect(
			$this->config['host'],
			$this->config['port'],
			$this->config['timeout']
		);
	}

	public function read($id) : string
	{
		return (string) $this->redis->get($this->getKey($id));
	}

	public function write($id, $data) : bool
	{
		return $this->redis->setex($this->getKey($id), $this->getLifetime(), $data);
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->redis->setex($this->getKey($id), $this->getLifetime(), $data);
	}

	public function close() : bool
	{
		$this->redis->close();
		$this->redis = null;
		return true;
	}

	public function destroy($id) : bool
	{
		$this->redis->del($this->getKey($id));
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}
}
