<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Session\SaveHandlers;

use Closure;
use Framework\Database\Database;
use Framework\Database\Manipulation\Delete;
use Framework\Database\Manipulation\Select;
use Framework\Database\Manipulation\Update;
use Framework\Log\LogLevel;
use Framework\Session\SaveHandler;
use SensitiveParameter;

/**
 * Class DatabaseHandler.
 *
 * ```sql
 * CREATE TABLE `Sessions` (
 *     `id` varchar(128) NOT NULL,
 *     `timestamp` timestamp NOT NULL,
 *     `data` blob NOT NULL,
 *     `ip` varchar(45) NOT NULL, -- optional
 *     `ua` varchar(255) NOT NULL, -- optional
 *     PRIMARY KEY (`id`),
 *     KEY `timestamp` (`timestamp`),
 *     KEY `ip` (`ip`), -- optional
 *     KEY `ua` (`ua`) -- optional
 * );
 * ```
 *
 * @package session
 */
class DatabaseHandler extends SaveHandler
{
    protected ?Database $database;

    /**
     * Prepare configurations to be used by the DatabaseHandler.
     *
     * @param array<string,mixed> $config Custom configs
     *
     * The custom configs are:
     *
     * ```php
     * $configs = [
     *     // The name of the table used for sessions
     *     'table' => 'Sessions',
     *     // The maxlifetime used for locking
     *     'maxlifetime' => null, // Null to use the ini value of session.gc_maxlifetime
     *     // The custom column names as values
     *     'columns' => [
     *         'id' => 'id',
     *         'data' => 'data',
     *         'timestamp' => 'timestamp',
     *         'ip' => 'ip',
     *         'ua' => 'ua',
     *     ],
     *     // Match IP?
     *     'match_ip' => false,
     *     // Match User-Agent?
     *     'match_ua' => false,
     *     // Independent of match_ip, save the initial IP in the ip column?
     *     'save_ip' => false,
     *     // Independent of match_ua, save the initial User-Agent in the ua column?
     *     'save_ua' => false,
     * ];
     * ```
     *
     * NOTE: The Database::connect configs was not shown.
     */
    protected function prepareConfig(#[SensitiveParameter] array $config) : void
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
                'user_id' => 'user_id',
            ],
            'match_ip' => false,
            'match_ua' => false,
            'save_ip' => false,
            'save_ua' => false,
            'save_user_id' => false,
        ], $config);
    }

    public function setDatabase(Database $database) : static
    {
        $this->database = $database;
        return $this;
    }

    public function getDatabase() : ?Database
    {
        return $this->database ?? null;
    }

    /**
     * Get the table name based on custom/default configs.
     *
     * @return string The table name
     */
    protected function getTable() : string
    {
        return $this->config['table'];
    }

    /**
     * Get a column name based on custom/default configs.
     *
     * @param string $key The columns config key
     *
     * @return string The column name
     */
    protected function getColumn(string $key) : string
    {
        return $this->config['columns'][$key];
    }

    /**
     * Adds the `WHERE $column = $value` clauses when matching IP or User-Agent.
     *
     * @param Delete|Select|Update $statement The statement to add the WHERE clause
     */
    protected function addWhereMatchs(Delete | Select | Update $statement) : void
    {
        if ($this->config['match_ip']) {
            $statement->whereEqual($this->getColumn('ip'), $this->getIP());
        }
        if ($this->config['match_ua']) {
            $statement->whereEqual($this->getColumn('ua'), $this->getUA());
        }
    }

    /**
     * Adds the optional `user_id` column.
     *
     * @param array<string,Closure|string> $columns The statement columns to insert/update
     */
    protected function addUserIdColumn(array &$columns) : void
    {
        if ($this->config['save_user_id']) {
            $key = $this->getColumn('user_id');
            $columns[$key] = $_SESSION[$key] ?? null;
        }
    }

    public function open($path, $name) : bool
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
            $this->setFingerprint('');
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
        $this->setFingerprint($data);
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
        if ($this->sessionExists) {
            return $this->writeUpdate($id, $data);
        }
        return $this->writeInsert($id, $data);
    }

    protected function writeInsert(string $id, string $data) : bool
    {
        $columns = [
            $this->getColumn('id') => $id,
            $this->getColumn('timestamp') => static function () : string {
                return 'NOW()';
            },
            $this->getColumn('data') => $data,
        ];
        if ($this->config['match_ip'] || $this->config['save_ip']) {
            $columns[$this->getColumn('ip')] = $this->getIP();
        }
        if ($this->config['match_ua'] || $this->config['save_ua']) {
            $columns[$this->getColumn('ua')] = $this->getUA();
        }
        $this->addUserIdColumn($columns);
        $inserted = $this->database
            ->insert($this->getTable())
            ->set($columns)
            ->run();
        if ($inserted === 0) {
            return false;
        }
        $this->setFingerprint($data);
        $this->sessionExists = true;
        return true;
    }

    protected function writeUpdate(string $id, string $data) : bool
    {
        $columns = [
            $this->getColumn('timestamp') => static function () : string {
                return 'NOW()';
            },
        ];
        if ( ! $this->hasSameFingerprint($data)) {
            $columns[$this->getColumn('data')] = $data;
        }
        $this->addUserIdColumn($columns);
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
                LogLevel::DEBUG
            );
        }
        return true;
    }

    public function gc($max_lifetime) : int | false
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
        // @phpstan-ignore-next-line
        return $this->database
            ->delete()
            ->from($this->getTable())
            ->whereLessThan(
                $this->getColumn('timestamp'),
                static function () use ($max_lifetime) : string {
                    return 'NOW() - INTERVAL ' . $max_lifetime . ' second';
                }
            )->run();
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
        if ($row && $row->locked) { // @phpstan-ignore-line
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
                    $lockId = $database->quote($this->lockId);
                    return "RELEASE_LOCK({$lockId})";
                },
            ])->run()
            ->fetch();
        if ($row && $row->unlocked) { // @phpstan-ignore-line
            $this->lockId = false;
            return true;
        }
        $this->log('Session (database): Error while trying to unlock ' . $this->lockId);
        return false;
    }
}
