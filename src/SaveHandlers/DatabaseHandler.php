<?php namespace Framework\Session\SaveHandlers;

use Framework\Database\Database;
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
class DatabaseHandler extends SaveHandler
{
	protected ?Database $database;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace_recursive([
			'table' => 'Sessions',
			'maxlifetime' => null,
			'columns' => [
				'id' => 'id',
				'data' => 'data',
				'timestamp' => 'timestamp',
			],
		], $config);
	}

	protected function getTable() : string
	{
		return $this->config['table'];
	}

	protected function getColumn(string $key) : string
	{
		return $this->config['columns'][$key];
	}

	public function open($path, $session_name) : bool
	{
		$this->database = new Database($this->config);
		return true;
	}

	public function read($id) : string
	{
		if ( ! isset($this->database) || $this->lock($id) === false) {
			$this->fingerprint = \md5('');
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		$row = $this->database
			->select()
			->from($this->getTable())
			->whereEqual($this->getColumn('id'), $id)
			->limit(1)
			->run()
			->fetch();
		$this->sessionExists = (bool) $row;
		$data = $row->data ?? '';
		$this->fingerprint = \md5($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ( ! isset($this->database)) {
			return false;
		}
		if ($this->lockId === false) {
			return false;
		}
		if ($id !== $this->sessionId) {
			$this->sessionExists = false;
			$this->sessionId = $id;
		}
		if ($this->sessionExists === false) {
			$inserted = $this->database
				->insert($this->getTable())
				->set([
					$this->getColumn('id') => $id,
					$this->getColumn('timestamp') => static function () : string {
						return 'NOW()';
					},
					$this->getColumn('data') => $data,
				])->run();
			if ($inserted === 0) {
				return false;
			}
			$this->fingerprint = \md5($data);
			$this->sessionExists = true;
			return true;
		}
		$columns = [
			$this->getColumn('timestamp') => static function () {
				return 'NOW()';
			},
		];
		if ($this->fingerprint !== \md5($data)) {
			$columns[$this->getColumn('data')] = $data;
		}
		$this->database
			->update()
			->table($this->getTable())
			->set($columns)
			->whereEqual($this->getColumn('id'), $id)
			->limit(1)
			->run();
		return true;
	}

	public function updateTimestamp($id, $data) : bool
	{
		$this->database
			->update()
			->table($this->getTable())
			->set([
				$this->getColumn('timestamp') => static function () : string {
					return 'NOW()';
				},
			])
			->whereEqual('id', $id)
			->limit(1)
			->run();
		return true;
	}

	public function close() : bool
	{
		return ! ($this->lockId && ! $this->unlock());
	}

	public function destroy($id) : bool
	{
		$this->database
			->delete()
			->from($this->getTable())
			->whereEqual($this->getColumn('id'), $id)
			->limit(1)
			->run();
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		$this->database
			->delete()
			->from($this->getTable())
			->whereLessThan(
				$this->getColumn('timestamp'),
				static function () use ($max_lifetime) : string {
					return 'NOW() - INTERVAL ' . $max_lifetime . ' second';
				}
			)->run();
		return true;
	}

	protected function lock(string $id) : bool
	{
		$row = $this->database
			->select()
			->expressions([
				'locked' => function (Database $database) use ($id) : string {
					$id = $database->quote($id);
					$maxlifetime = $database->quote($this->getMaxlifetime());
					return "GET_LOCK({$id}, {$maxlifetime})";
				},
			])->run()
			->fetch();
		if ($row && $row->locked) {
			$this->lockId = $id;
			return true;
		}
		$this->log('Session (database): Error while trying to lock ' . $id);
		return false;
	}

	protected function unlock() : bool
	{
		if ($this->lockId === false) {
			return true;
		}
		$row = $this->database
			->select()
			->expressions([
				'unlocked' => function (Database $database) : string {
					$lock_id = $database->quote($this->lockId);
					return "RELEASE_LOCK({$lock_id})";
				},
			])->run()
			->fetch();
		if ($row && $row->unlocked) {
			$this->lockId = false;
			return true;
		}
		$this->log('Session (database): Error while trying to unlock ' . $this->lockId);
		return false;
	}
}
