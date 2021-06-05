<?php namespace Framework\Session\SaveHandlers;

use Framework\Database\Database as DB;
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
	protected DB $database;
	protected \stdClass $row;
	protected string | false $lock = false;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'table' => 'Sessions',
		], $config);
	}

	public function updateTimestamp($id, $data) : bool
	{
		$query = $this->database
			->update()
			->table($this->config['table'])
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
		return ! ($this->lock && ! $this->releaseLock());
	}

	public function destroy($id) : bool
	{
		$query = $this->database
			->delete()
			->from($this->config['table'])
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
		$this->database
			->delete()
			->from($this->config['table'])
			->whereLessThan('timestamp', $max_lifetime)
			->run();
		return true;
	}

	public function open($path, $session_name) : bool
	{
		$this->database = new DB($this->config);
		return true;
	}

	public function read($id) : string
	{
		if ($this->getLock($id) === false) {
			return '';
		}
		$query = $this->database
			->select()
			->from($this->config['table'])
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
		$query = $this->database
			->replace()
			->into($this->config['table']);
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

	protected function getLock(string $session_id) : bool
	{
		$lock_id = $session_id;
		$lock_id .= $this->matchIP ? '-' . $this->getIP() : '';
		$lock_id .= $this->matchUA ? '-' . $this->getUA() : '';
		$lock_id = \md5($lock_id);
		$locked = $this->database
			->select()
			->expressions([
				'locked' => static function (DB $db) use ($lock_id) {
					$lock_id = $db->quote($lock_id);
					return "GET_LOCK({$lock_id}, 300)";
				},
			])->run()
			->fetch()
			->locked;
		if ($locked) {
			$this->lock = $lock_id;
			return true;
		}
		return false;
	}

	protected function releaseLock() : bool
	{
		if ($this->lock === false) {
			return true;
		}
		$lock_id = $this->lock;
		$unlocked = $this->database
			->select()
			->expressions([
				'unlocked' => static function (DB $db) use ($lock_id) {
					$lock_id = $db->quote($lock_id);
					return "RELEASE_LOCK({$lock_id})";
				},
			])->run()
			->fetch()
			->unlocked;
		if ($unlocked) {
			$this->lock = false;
			return true;
		}
		return false;
	}
}
