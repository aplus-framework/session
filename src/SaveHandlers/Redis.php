<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

class Redis extends SaveHandler
{
	protected ?\Redis $redis;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => '',
			'host' => '127.0.0.1',
			'port' => 6379,
			'timeout' => 0.0,
			'lock_attempts' => 60,
			'lock_ttl' => 600,
			'maxlifetime' => null,
		], $config);
	}

	protected function getKey(string $id) : string
	{
		return $this->config['prefix'] . $id;
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
		if ( ! isset($this->redis) || ! ($l = $this->getLock($id))) {
			\var_dump($l);
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		$data = $this->redis->get($this->getKey($id));
		\is_string($data) ? $this->sessionExists = true : $data = '';
		$this->fingerprint = \md5($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ( ! isset($this->redis)) {
			return false;
		}
		if ($id !== $this->sessionId) {
			if ( ! $this->releaseLock() || ! $this->getLock($id)) {
				return false;
			}
			$this->sessionExists = false;
			$this->sessionId = $id;
		}
		if ($this->lockId === false) {
			return false;
		}
		$maxlifetime = $this->getMaxlifetime();
		$this->redis->expire($this->lockId, $this->config['lock_ttl']);
		$fingerprint = \md5($data);
		if ($this->fingerprint !== $fingerprint || $this->sessionExists === false) {
			if ($this->redis->set($this->getKey($id), $data, $maxlifetime)) {
				$this->fingerprint = $fingerprint;
				$this->sessionExists = true;
				return true;
			}
			return false;
		}
		return $this->redis->expire($this->getKey($id), $maxlifetime);
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->redis->setex($this->getKey($id), $this->getMaxlifetime(), $data);
	}

	public function close() : bool
	{
		if ( ! isset($this->redis)) {
			return true;
		}
		try {
			$ping = $this->redis->ping();
			if ($ping === true || $ping === '+PONG') {
				if ($this->lockId) {
					$this->redis->del($this->lockId);
				}
				if ( ! $this->redis->close()) {
					return false;
				}
			}
		} catch (\RedisException $e) {
			// TODO: log
		}
		$this->redis = null;
		return true;
	}

	public function destroy($id) : bool
	{
		if ( ! $this->lockId) {
			return false;
		}
		$this->redis->del($this->getKey($id));
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}

	protected function getLock(string $id) : bool
	{
		$ttl = $this->config['lock_ttl'];
		if ($this->lockId && $this->redis->get($this->lockId)) {
			return $this->redis->expire($this->lockId, $ttl);
		}
		$lock_id = $this->getKey($id) . ':lock';
		$attempt = 0;
		while ($attempt < $this->config['lock_attempts']) {
			$attempt++;
			$old_ttl = $this->redis->ttl($lock_id);
			if ($old_ttl > 0) {
				\sleep(1);
				continue;
			}
			if ( ! $this->redis->setex($lock_id, $ttl, (string) \time())) {
				return false;
			}
			$this->lockId = $lock_id;
			break;
		}
		return $attempt !== $this->config['lock_attempts'];
	}

	protected function releaseLock() : bool
	{
		if ($this->lockId === false) {
			return true;
		}
		if ( ! $this->redis->del($this->lockId)) {
			return false;
		}
		$this->lockId = false;
		return true;
	}
}
