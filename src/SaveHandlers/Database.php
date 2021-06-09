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
	protected string $sessionId;
	protected string | false $lockId = false;
	protected string $fingerprint;
	protected bool $rowExists;

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
			$this->fingerprint = \md5('');
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		$row = $this->database
			->select()
			->from($this->config['table'])
			->whereEqual('id', $id)
			->limit(1)
			->run()
			->fetch();
		$this->rowExists = (bool) $row;
		$data = $row->data ?? '';
		$this->fingerprint = \md5($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ($this->lockId === false) {
			return false;
		}
		if ($id !== $this->sessionId) {
			$this->rowExists = false;
			$this->sessionId = $id;
		}
		if ($this->rowExists === false) {
			$inserted = $this->database
				->insert($this->config['table'])
				->set([
					'id' => $id,
					'timestamp' => static function () {
						return 'NOW()';
					},
					'data' => $data,
				])->run();
			if ($inserted === 0) {
				return false;
			}
			$this->fingerprint = \md5($data);
			$this->rowExists = true;
			return true;
		}
		$columns = [
			'timestamp' => static function () {
				return 'NOW()';
			},
		];
		if ($this->fingerprint !== \md5($data)) {
			$columns['data'] = $data;
		}
		$this->database
			->update()
			->table($this->config['table'])
			->set($columns)
			->whereEqual('id', $id)
			->limit(1)
			->run();
		return true;
	}

	protected function getLock(string $id) : bool
	{
		$row = $this->database
			->select()
			->expressions([
				'locked' => function (DB $db) use ($id) {
					$id = $db->quote($id);
					$lifetime = $db->quote($this->getLifetime());
					return "GET_LOCK({$id}, {$lifetime})";
				},
			])->run()
			->fetch();
		if ($row && $row->locked) {
			$this->lockId = $id;
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
