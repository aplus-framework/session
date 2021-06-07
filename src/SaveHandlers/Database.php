<?php namespace Framework\Session\SaveHandlers;

use Framework\Database\Database as DB;
use Framework\Session\SaveHandler;

/**
 * Class Database.
 *
 * ```sql
 * CREATE TABLE `Sessions` (
 *     `id` varchar(128) NOT NULL,
 *     `timestamp` int(10) unsigned NOT NULL,
 *     `data` blob NOT NULL,
 *     PRIMARY KEY (`id`),
 *     KEY `timestamp` (`timestamp`)
 * );
 * ```
 */
class Database extends SaveHandler
{
	protected DB $database;
	protected \stdClass $row;
	protected string | false $lockId = false;

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
			->whereEqual('id', $id)
			->limit(1)
			->run();
		return true;
	}

	public function close() : bool
	{
		return ! ($this->lockId && ! $this->releaseLock());
	}

	public function destroy($id) : bool
	{
		$this->database
			->delete()
			->from($this->config['table'])
			->whereEqual('id', $id)
			->limit(1)
			->run();
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
		$query->set($set)->run();
		return true;
	}

	protected function getLock(string $session_id) : bool
	{
		$row = $this->database
			->select()
			->expressions([
				'locked' => function (DB $db) use ($session_id) {
					$session_id = $db->quote($session_id);
					$lifetime = $db->quote($this->getLifetime());
					return "GET_LOCK({$session_id}, {$lifetime})";
				},
			])->run()
			->fetch();
		if ($row && $row->locked) {
			$this->lockId = $session_id;
			return true;
		}
		return false;
	}

	protected function releaseLock() : bool
	{
		if ($this->lockId === false) {
			return true;
		}
		$row = $this->database
			->select()
			->expressions([
				'unlocked' => function (DB $db) {
					$lock_id = $db->quote($this->lockId);
					return "RELEASE_LOCK({$lock_id})";
				},
			])->run()
			->fetch();
		if ($row && $row->unlocked) {
			$this->lockId = false;
			return true;
		}
		return false;
	}
}
