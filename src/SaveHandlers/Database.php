<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

/**
 * Class Database.
 *
 * CREATE TABLE IF NOT EXISTS `Sessions` (
 * `id` varchar(128) NOT NULL,
 * `ip` varchar(45),
 * `ua` varchar(255),
 * `timestamp` int(10) unsigned NOT NULL,
 * `data` blob NOT NULL,
 * PRIMARY KEY (`id`),
 * KEY `ip` (`ip`),
 * KEY `ua` (`ua`),
 * KEY `timestamp` (`timestamp`)
 * );
 *
 * @property \Framework\Database\Database[] $handler
 */
class Database extends SaveHandler
{
	protected \stdClass $row;
	protected string $table;

	public function __construct($handler, bool $match_ip = false, bool $match_ua = false)
	{
		if ( ! \is_array($handler)) {
			$handler = [
				'read' => $handler,
				'write' => $handler,
			];
		}
		$this->table = $handler['table'] ?? 'Sessions';
		unset($handler['table']);
		parent::__construct($handler, $match_ip, $match_ua);
	}

	protected function getDatabase(string $connection_type) : \Framework\Database\Database
	{
		return $this->handler[$connection_type];
	}

	public function updateTimestamp($id, $data) : bool
	{
		$query = $this->getDatabase('write')
			->update()
			->table($this->table)
			->set([
				'timestamp' => \time(),
				'data' => $data,
			])
			->whereEqual('id', $id);
		if ($this->matchIP) {
			$ip = $this->getIP();
			$ip === null
				? $query->whereIsNull('ip')
				: $query->whereEqual('ip', $ip);
		}
		if ($this->matchUA) {
			$ua = $this->getUA();
			$ua === null
				? $query->whereIsNull('ua')
				: $query->whereEqual('ua', $ua);
		}
		$query->limit(1)->run();
		return true;
	}

	public function close() : bool
	{
		return true;
	}

	public function destroy($id) : bool
	{
		$query = $this->getDatabase('write')
			->delete()
			->from($this->table)
			->whereEqual('id', $id);
		if ($this->matchIP) {
			$ip = $this->getIP();
			$ip === null
				? $query->whereIsNull('ip')
				: $query->whereEqual('ip', $ip);
		}
		if ($this->matchUA) {
			$ua = $this->getUA();
			$ua === null
				? $query->whereIsNull('ua')
				: $query->whereEqual('ua', $ua);
		}
		$query->limit(1)->run();
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		$max_lifetime = \time() - $max_lifetime;
		$this->getDatabase('write')
			->delete()
			->from($this->table)
			->whereLessThan('timestamp', $max_lifetime)
			->run();
		return true;
	}

	public function open($path, $session_name) : bool
	{
		return $this->getDatabase('read') && $this->getDatabase('write');
	}

	public function read($id) : string
	{
		$query = $this->getDatabase('read')
			->select()
			->from($this->table)
			->whereEqual('id', $id);
		if ($this->matchIP) {
			$ip = $this->getIP();
			$ip === null
				? $query->whereIsNull('ip')
				: $query->whereEqual('ip', $ip);
		}
		if ($this->matchUA) {
			$ua = $this->getUA();
			$ua === null
				? $query->whereIsNull('ua')
				: $query->whereEqual('ua', $ua);
		}
		$lifetime = $this->getLifetime();
		if ($lifetime > 0) {
			$lifetime = \time() - $lifetime;
			$query->whereGreaterThan('timestamp', $lifetime);
		}
		$query->limit(1);
		$result = $query->run()->fetch();
		if ($result) {
			$this->row = $result;
			return $this->row->data;
		}
		return '';
	}

	public function write($id, $data) : bool
	{
		$query = $this->getDatabase('write')
			->replace()
			->into($this->table);
		$set = [
			'id' => $id,
			'data' => $data,
			'timestamp' => \time(),
		];
		if ($this->matchIP) {
			$set['ip'] = $this->row->ip ?? $this->getIP();
		}
		if ($this->matchUA) {
			$set['ua'] = $this->row->ua ?? $this->getUA();
		}
		$query->set($set)->run();
		return true;
	}
}
