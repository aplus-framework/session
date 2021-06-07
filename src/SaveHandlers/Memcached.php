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
		return (string) $this->memcached->get($this->getKey($id));
	}

	public function write($id, $data) : bool
	{
		return $this->memcached->set(
			$this->getKey($id),
			$data,
			$this->getLifetime()
		);
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
		$this->memcached->quit();
		$this->memcached = null;
		return true;
	}

	public function destroy($id) : bool
	{
		$destroyed = $this->memcached->delete($id);
		if ($destroyed === false
			&& $this->memcached->getResultCode() !== $this->memcached::RES_NOTFOUND
		) {
			return false;
		}
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		return true;
	}
}
