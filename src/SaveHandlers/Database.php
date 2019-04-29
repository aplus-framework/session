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
	/**
	 * @var \stdClass
	 */
	protected $row;

	public function __construct($handler, bool $match_ip = false, bool $match_ua = false)
	{
		if ( ! \is_array($handler)) {
			$handler = [
				'read' => $handler,
				'write' => $handler,
			];
		}
		parent::__construct($handler, $match_ip, $match_ua);
	}

	protected function getDatabase(string $connection_type) : \Framework\Database\Database
	{
		return $this->handler[$connection_type];
	}

	protected function getProtectedTable(string $connection_type) : string
	{
		return $this->getDatabase($connection_type)->protectIdentifier('Sessions');
	}

	public function updateTimestamp($session_id, $session_data) : bool
	{
		$id = $this->getDatabase('write')->quote($session_id);
		$data = $this->getDatabase('write')->quote($session_data);
		$timestamp = \time();
		$sql = 'UPDATE ' . $this->getProtectedTable('write')
			. ' SET `timestamp` = ' . $timestamp
			. ', `data` = ' . $data
			. ' WHERE `id` = ' . $id;
		if ($this->matchIP) {
			$ip = $this->getDatabase('write')->quote($this->getIP());
			$sql .= $ip === 'NULL'
				? ' AND `ip` IS NULL'
				: ' AND `ip` = ' . $ip;
		}
		if ($this->matchUA) {
			$ua = $this->getDatabase('write')->quote($this->getUA());
			$sql .= $ua === 'NULL'
				? ' AND `ua` IS NULL'
				: ' AND `ua` = ' . $ua;
		}
		$sql .= ' LIMIT 1';
		$this->getDatabase('write')->exec($sql);
		return true;
	}

	public function close() : bool
	{
		return true;
	}

	public function destroy($session_id) : bool
	{
		$id = $this->getDatabase('write')->quote($session_id);
		$sql = 'DELETE FROM ' . $this->getProtectedTable('write') . ' WHERE `id` = ' . $id;
		if ($this->matchIP) {
			$ip = $this->getDatabase('write')->quote($this->getIP());
			$sql .= $ip === 'NULL'
				? ' AND `ip` IS NULL'
				: ' AND `ip` = ' . $ip;
		}
		if ($this->matchUA) {
			$ua = $this->getDatabase('write')->quote($this->getUA());
			$sql .= $ua === 'NULL'
				? ' AND `ua` IS NULL'
				: ' AND `ua` = ' . $ua;
		}
		$sql .= ' LIMIT 1';
		$this->getDatabase('write')->exec($sql);
		return true;
	}

	public function gc($maxlifetime) : bool
	{
		$maxlifetime = \time() - $maxlifetime;
		$maxlifetime = $this->getDatabase('write')->quote($maxlifetime);
		$sql = 'DELETE FROM ' . $this->getProtectedTable('write')
			. ' WHERE `timestamp` < ' . $maxlifetime;
		$this->getDatabase('write')->exec($sql);
		return true;
	}

	public function open($save_path, $session_name) : bool
	{
		return $this->getDatabase('read') && $this->getDatabase('write');
	}

	public function read($session_id) : string
	{
		$id = $this->getDatabase('read')->quote($session_id);
		$sql = 'SELECT * FROM ' . $this->getProtectedTable('read') . ' WHERE `id` = ' . $id;
		if ($this->matchIP) {
			$ip = $this->getDatabase('read')->quote($this->getIP());
			$sql .= $ip === 'NULL'
				? ' AND `ip` IS NULL'
				: ' AND `ip` = ' . $ip;
		}
		if ($this->matchUA) {
			$ua = $this->getDatabase('read')->quote($this->getUA());
			$sql .= $ua === 'NULL'
				? ' AND `ua` IS NULL'
				: ' AND `ua` = ' . $ua;
		}
		$lifetime = $this->getLifetime();
		if ($lifetime > 0) {
			$lifetime = \time() - $lifetime;
			$lifetime = $this->getDatabase('read')->quote($lifetime);
			$sql .= ' AND `timestamp` > ' . $lifetime;
		}
		$sql .= ' LIMIT 1';
		$result = $this->getDatabase('read')->query($sql);
		if ($result && $result = $result->fetch()) {
			$this->row = $result;
			return $this->row->data;
		}
		return '';
	}

	public function write($session_id, $session_data) : bool
	{
		$id = $this->getDatabase('write')->quote($session_id);
		$data = $this->getDatabase('write')->quote($session_data);
		$timestamp = \time();
		$sql = 'REPLACE INTO ' . $this->getProtectedTable('write')
			. ' SET `id` = ' . $id
			. ', `data`= ' . $data
			. ', `timestamp` = ' . $timestamp;
		if ($this->matchIP) {
			$ip = $this->getDatabase('write')->quote(
				$this->row->ip ?? $this->getIP()
			);
			$sql .= ', `ip` = ' . $ip;
		}
		if ($this->matchUA) {
			$ua = $this->getDatabase('write')->quote(
				$this->row->ua ?? $this->getUA()
			);
			$sql .= ', `ua` = ' . $ua;
		}
		$this->getDatabase('write')->exec($sql);
		return true;
	}
}
