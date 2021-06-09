<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

class Redis extends SaveHandler
{
	protected ?\Redis $redis;
	protected string $sessionId;
	protected string | false $lockId = false;
	protected string $fingerprint;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => '',
			'host' => '127.0.0.1',
			'port' => 6379,
			'timeout' => 0.0,
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
		if (isset($this->redis) && $this->getLock($id)) {
			if ( ! isset($this->sessionId)) {
				$this->sessionId = $id;
			}
			$data = $this->redis->get($this->getKey($id));
			\is_string($data) ? $this->keyExists = true : $data = '';
			$this->fingerprint = \md5($data);
			return $data;
		}
		return '';
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
			$this->keyExists = false;
			$this->sessionId = $id;
		}
		if ($this->lockId === false) {
			return false;
		}
		$lifetime = $this->getLifetime();
		$this->redis->expire($this->lockId, $lifetime);
		$fingerprint = \md5($data);
		if ($this->fingerprint !== $fingerprint || $this->keyExists === false) {
			if ($this->redis->set($this->getKey($id), $data, $lifetime)) {
				$this->fingerprint = $fingerprint;
				$this->keyExists = true;
				return true;
			}
			return false;
		}
		return $this->redis->expire($this->getKey($id), $lifetime);
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->redis->setex($this->getKey($id), $this->getLifetime(), $data);
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
		$expiration = $this->getLifetime() + 30;
		if ($this->lockId && $this->redis->get($this->lockId)) {
			return $this->redis->expire($this->lockId, $expiration);
		}
		$lock_id = $this->getKey($id) . ':lock';
		$attempt = 0;
		while ($attempt < $expiration) {
			$attempt++;
			$ttl = $this->redis->ttl($lock_id);
			if ($ttl > 0) {
				\sleep(1);
				continue;
			}
			if ( ! $this->redis->setex($lock_id, $expiration, (string) \time())) {
				return false;
			}
			$this->lockId = $lock_id;
			break;
		}
		return $attempt !== $expiration;
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
