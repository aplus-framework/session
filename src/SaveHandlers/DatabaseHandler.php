<?php namespace Framework\Session\SaveHandlers;

use Framework\Database\Database;
use Framework\Database\Manipulation\Delete;
use Framework\Database\Manipulation\Select;
use Framework\Database\Manipulation\Update;
use Framework\Log\Logger;
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
				'ip' => 'ip',
				'ua' => 'ua',
			],
			'match_ip' => false,
			'match_ua' => false,
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

	protected function addWhereMatchs(Delete | Select | Update $statement) : void
	{
		if ($this->config['match_ip']) {
			$statement->whereEqual($this->getColumn('ip'), $this->getIP());
		}
		if ($this->config['match_ua']) {
			$statement->whereEqual($this->getColumn('ua'), $this->getUA());
		}
	}

	public function open($path, $session_name) : bool
	{
		try {
			$this->database ??= new Database($this->config);
			return true;
		} catch (\Exception $exception) {
			$this->log(
				'Session (database): Thrown a ' . \get_class($exception)
				. ' while trying to open: ' . $exception->getMessage()
			);
		}
		return false;
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
		$statement = $this->database
			->select()
			->from($this->getTable())
			->whereEqual($this->getColumn('id'), $id);
		$this->addWhereMatchs($statement);
		$row = $statement->limit(1)->run()->fetch();
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
			$columns = [
				$this->getColumn('id') => $id,
				$this->getColumn('timestamp') => static function () : string {
					return 'NOW()';
				},
				$this->getColumn('data') => $data,
			];
			if ($this->config['match_ip']) {
				$columns[$this->getColumn('ip')] = $this->getIP();
			}
			if ($this->config['match_ua']) {
				$columns[$this->getColumn('ua')] = $this->getUA();
			}
			$inserted = $this->database
				->insert($this->getTable())
				->set($columns)
				->run();
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
		$statement = $this->database
			->update()
			->table($this->getTable())
			->set($columns)
			->whereEqual($this->getColumn('id'), $id);
		$this->addWhereMatchs($statement);
		$statement->run();
		return true;
	}

	public function updateTimestamp($id, $data) : bool
	{
		$statement = $this->database
			->update()
			->table($this->getTable())
			->set([
				$this->getColumn('timestamp') => static function () : string {
					return 'NOW()';
				},
			])
			->whereEqual($this->getColumn('id'), $id);
		$this->addWhereMatchs($statement);
		$statement->run();
		return true;
	}

	public function close() : bool
	{
		$closed = ! ($this->lockId && ! $this->unlock());
		$this->database = null;
		return $closed;
	}

	public function destroy($id) : bool
	{
		$statement = $this->database
			->delete()
			->from($this->getTable())
			->whereEqual($this->getColumn('id'), $id);
		$this->addWhereMatchs($statement);
		$result = $statement->run();
		if ($result !== 1) {
			$this->log(
				'Session (database): Expected to delete 1 row, deleted ' . $result,
				Logger::DEBUG
			);
		}
		return true;
	}

	public function gc($max_lifetime) : bool
	{
		try {
			$this->database ??= new Database($this->config);
		} catch (\Exception $exception) {
			$this->log(
				'Session (database): Thrown a ' . \get_class($exception)
				. ' while trying to gc: ' . $exception->getMessage()
			);
			return false;
		}
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
