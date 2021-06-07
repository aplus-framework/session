<?php namespace Framework\Session\SaveHandlers;

use Framework\Database\Database as DB;
use Framework\Session\SaveHandler;

/**
 * Class Database.
 *
 * ```sql
 * CREATE TABLE `Sessions` (
 *     `id` varchar(128) NOT NULL,
 *     `timestamp` timestamp NOT NULL,
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
		$this->database
			->update()
			->table($this->config['table'])
			->set([
				'timestamp' => static function () {
					return 'NOW()';
				},
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
		$this->database
			->delete()
			->from($this->config['table'])
			->whereLessThan('timestamp', static function () use ($max_lifetime) {
				return 'NOW() - INTERVAL ' . $max_lifetime . ' second';
			})
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
			$query->whereGreaterThan('timestamp', static function () use ($lifetime) {
				return 'NOW() - INTERVAL ' . $lifetime . ' second';
			});
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
			'timestamp' => static function () {
				return 'NOW()';
			},
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
