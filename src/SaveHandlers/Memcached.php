<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;
use OutOfBoundsException;

class Memcached extends SaveHandler
{
	protected ?\Memcached $memcached;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace_recursive([
			'prefix' => '',
			'servers' => [
				[
					'host' => '127.0.0.1',
					'port' => 11211,
					'weight' => 0,
				],
			],
			'options' => [
				\Memcached::OPT_BINARY_PROTOCOL => true,
			],
		], $config);
		foreach ($this->config['servers'] as $index => $server) {
			if ( ! isset($server['host'])) {
				throw new OutOfBoundsException(
					"Memcached host not set on server config '{$index}'"
				);
			}
		}
	}

	protected function getKey(string $id) : string
	{
		return $this->config['prefix'] . $id;
	}

	public function open($path, $name) : bool
	{
		if (isset($this->memcached)) {
			return true;
		}
		$this->memcached = new \Memcached();
		foreach ($this->config['servers'] as $server) {
			$result = $this->memcached->addServer(
				$server['host'],
				$server['port'] ?? 11211,
				$server['weight'] ?? 0,
			);
			if ($result === false) {
				// TODO: Log
				return false;
			}
		}
		$result = $this->memcached->setOptions($this->config['options']);
		if ($result === false) {
			// TODO: Log
			return false;
		}
		if ($this->memcached->getStats() === false) {
			throw new \RuntimeException('Memcached could not connect to any server');
		}
		return true;
	}

	public function read($id) : string
	{
		if ( ! isset($this->memcached) || ! $this->getLock($id)) {
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		$data = (string) $this->memcached->get($this->getKey($id));
		$this->fingerprint = \md5($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ( ! isset($this->memcached)) {
			return false;
		}
		if ($id !== $this->sessionId) {
			if ( ! $this->releaseLock() || ! $this->getLock($id)) {
				return false;
			}
			$this->fingerprint = \md5('');
			$this->sessionId = $id;
		}
		if ($this->lockId === false) {
			return false;
		}
		$lifetime = $this->getLifetime();
		$this->memcached->replace($this->lockId, \time(), $lifetime);
		$fingerprint = \md5($data);
		if ($this->fingerprint !== $fingerprint) {
			if ($this->memcached->set($this->getKey($id), $data, $lifetime)) {
				$this->fingerprint = $fingerprint;
				return true;
			}
			return false;
		}
		return $this->memcached->touch($this->getKey($id), $lifetime);
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->memcached->touch(
			$this->getKey($id),
			$this->getLifetime()
		);
	}

	public function close() : bool
	{
		if ($this->lockId) {
			$this->memcached->delete($this->lockId);
		}
		if ( ! $this->memcached->quit()) {
			return false;
		}
		$this->memcached = null;
		return true;
	}

	public function destroy($id) : bool
	{
		if ( ! $this->lockId) {
			return false;
		}
		$destroyed = $this->memcached->delete($this->getKey($id));
		return ! ($destroyed === false
			&& $this->memcached->getResultCode() !== $this->memcached::RES_NOTFOUND);
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}

	/**
	 * @param string $id
	 *
	 * @see https://www.php.net/manual/en/memcached.expiration.php
	 *
	 * @return bool
	 */
	protected function getLock(string $id) : bool
	{
		$max = 60 * 60 * 24 * 30;
		$expiration = $this->getLifetime() + 30;
		if ($expiration > $max) {
			$expiration = $max;
		}
		if ($this->lockId && $this->memcached->get($this->lockId)) {
			return $this->memcached->replace($this->lockId, \time(), $expiration);
		}
		$lock_id = $this->getKey($id) . ':lock';
		$attempt = 0;
		while ($attempt < $expiration) {
			$attempt++;
			if ($this->memcached->get($lock_id)) {
				\sleep(1);
				continue;
			}
			if ( ! $this->memcached->set($lock_id, \time(), $expiration)) {
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
		if ( ! $this->memcached->delete($this->lockId) &&
			$this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND
		) {
			return false;
		}
		$this->lockId = false;
		return true;
	}
}
