<?php namespace Framework\Session\SaveHandlers;

use Framework\Log\Logger;
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
		$connected = $this->redis->connect(
			$this->config['host'],
			$this->config['port'],
			$this->config['timeout']
		);
		if ($connected) {
			return true;
		}
		$this->log(
			'Session (redis): Could not connect to server ' . $this->config['host'] . ':' . $this->config['port']
		);
		return false;
	}

	public function read($id) : string
	{
		if ( ! isset($this->redis) || ! ($l = $this->lock($id))) {
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
			if ( ! $this->unlock() || ! $this->lock($id)) {
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
			$this->log('Session (redis): Got RedisException on close: ' . $e->getMessage());
		}
		$this->redis = null;
		return true;
	}

	public function destroy($id) : bool
	{
		if ( ! $this->lockId) {
			return false;
		}
		$result = $this->redis->del($this->getKey($id));
		if ($result !== 1) {
			$this->log(
				'Session (redis): Expected to delete 1 key, deleted ' . $result,
				Logger::DEBUG
			);
		}
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}

	protected function lock(string $id) : bool
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
				$this->log('Session (redis): Error while trying to lock ' . $lock_id);
				return false;
			}
			$this->lockId = $lock_id;
			break;
		}
		if ($attempt === $this->config['lock_attempts']) {
			$this->log(
				"Session (redis): Unable to lock {$lock_id} after {$attempt} attempts"
			);
			return false;
		}
		if (isset($old_ttl) && $old_ttl === -1) {
			$this->log(
				'Session (redis): Lock for ' . $this->getKey($id) . ' had not TTL',
				Logger::DEBUG
			);
		}
		return true;
	}

	protected function unlock() : bool
	{
		if ($this->lockId === false) {
			return true;
		}
		if ( ! $this->redis->del($this->lockId)) {
			$this->log('Session (redis): Error while trying to unlock ' . $this->lockId);
			return false;
		}
		$this->lockId = false;
		return true;
	}
}
