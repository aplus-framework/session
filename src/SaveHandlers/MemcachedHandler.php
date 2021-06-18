<?php namespace Framework\Session\SaveHandlers;

use Framework\Log\Logger;
use Framework\Session\SaveHandler;
use Memcached;
use OutOfBoundsException;

class MemcachedHandler extends SaveHandler
{
	protected ?Memcached $memcached;

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
				Memcached::OPT_BINARY_PROTOCOL => true,
			],
			'lock_attempts' => 60,
			'lock_ttl' => 600,
			'maxlifetime' => null,
			'match_ip' => false,
			'match_ua' => false,
		], $config);
		foreach ($this->config['servers'] as $index => $server) {
			if ( ! isset($server['host'])) {
				throw new OutOfBoundsException(
					"Memcached host not set on server config '{$index}'"
				);
			}
		}
	}

	/**
	 * Get expiration as a timestamp.
	 *
	 * Useful for Time To Lives greater than a month (`60*60*24*30`).
	 *
	 * @param int $seconds
	 *
	 * @see https://www.php.net/manual/en/memcached.expiration.php
	 *
	 * @return int
	 */
	protected function getExpiration(int $seconds) : int
	{
		return \time() + $seconds;
	}

	/**
	 * Get a key for Memcached, using the optional
	 * prefix, match IP and match User-Agent configs.
	 *
	 * NOTE: The max key length allowed by Memcached is 250 bytes.
	 *
	 * @param string $id The session id
	 *
	 * @return string The final key
	 */
	protected function getKey(string $id) : string
	{
		$key = $this->config['prefix'] . $id;
		if ($this->config['match_ip']) {
			$key .= ':' . $this->getIP();
		}
		if ($this->config['match_ua']) {
			$key .= ':' . \md5($this->getUA());
		}
		return $key;
	}

	public function open($path, $name) : bool
	{
		if (isset($this->memcached)) {
			return true;
		}
		$this->memcached = new Memcached();
		$pool = [];
		foreach ($this->config['servers'] as $server) {
			$host = $server['host'] . ':' . ($server['port'] ?? 11211);
			if (\in_array($host, $pool, true)) {
				$this->log(
					'Session (memcached): Server pool already has ' . $host,
					Logger::DEBUG
				);
				continue;
			}
			$result = $this->memcached->addServer(
				$server['host'],
				$server['port'] ?? 11211,
				$server['weight'] ?? 0,
			);
			if ($result === false) {
				$this->log("Session (memcached): Could not add {$host} to server pool");
				continue;
			}
			$pool[] = $host;
		}
		$result = $this->memcached->setOptions($this->config['options']);
		if ($result === false) {
			$this->log('Session (memcached): ' . $this->memcached->getLastErrorMessage());
		}
		if ( ! $this->memcached->getStats()) {
			$this->log('Session (memcached): Could not connect to any server');
			return false;
		}
		return true;
	}

	public function read($id) : string
	{
		if ( ! isset($this->memcached) || ! $this->lock($id)) {
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		$data = (string) $this->memcached->get($this->getKey($id));
		$this->setFingerprint($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ( ! isset($this->memcached)) {
			return false;
		}
		if ($id !== $this->sessionId) {
			if ( ! $this->unlock() || ! $this->lock($id)) {
				return false;
			}
			$this->setFingerprint('');
			$this->sessionId = $id;
		}
		if ($this->lockId === false) {
			return false;
		}
		$this->memcached->replace(
			$this->lockId,
			\time(),
			$this->getExpiration($this->config['lock_ttl'])
		);
		$maxlifetime = $this->getExpiration($this->getMaxlifetime());
		if ($this->hasSameFingerprint($data)) {
			return $this->memcached->touch($this->getKey($id), $maxlifetime);
		}
		if ($this->memcached->set($this->getKey($id), $data, $maxlifetime)) {
			$this->setFingerprint($data);
			return true;
		}
		return false;
	}

	public function updateTimestamp($id, $data) : bool
	{
		return $this->memcached->touch(
			$this->getKey($id),
			$this->getExpiration($this->getMaxlifetime())
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
			&& $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND);
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}

	protected function lock(string $id) : bool
	{
		$expiration = $this->getExpiration($this->config['lock_ttl']);
		if ($this->lockId && $this->memcached->get($this->lockId)) {
			return $this->memcached->replace($this->lockId, \time(), $expiration);
		}
		$lock_id = $this->getKey($id) . ':lock';
		$attempt = 0;
		while ($attempt < $this->config['lock_attempts']) {
			$attempt++;
			if ($this->memcached->get($lock_id)) {
				\sleep(1);
				continue;
			}
			if ( ! $this->memcached->set($lock_id, \time(), $expiration)) {
				$this->log('Session (memcached): Error while trying to lock ' . $lock_id);
				return false;
			}
			$this->lockId = $lock_id;
			break;
		}
		if ($attempt === $this->config['lock_attempts']) {
			$this->log(
				"Session (memcached): Unable to lock {$lock_id} after {$attempt} attempts"
			);
			return false;
		}
		return true;
	}

	protected function unlock() : bool
	{
		if ($this->lockId === false) {
			return true;
		}
		if ( ! $this->memcached->delete($this->lockId) &&
			$this->memcached->getResultCode() !== Memcached::RES_NOTFOUND
		) {
			$this->log('Session (memcached): Error while trying to unlock ' . $this->lockId);
			return false;
		}
		$this->lockId = false;
		return true;
	}
}
