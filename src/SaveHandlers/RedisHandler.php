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

use Framework\Log\LogLevel;
use Framework\Session\SaveHandler;
use Redis;
use RedisException;
use SensitiveParameter;

/**
 * Class RedisHandler.
 *
 * @package session
 */
class RedisHandler extends SaveHandler
{
    protected ?Redis $redis;

    /**
     * Prepare configurations to be used by the RedisHandler.
     *
     * @param array<string,mixed> $config Custom configs
     *
     * The custom configs are:
     *
     * ```php
     * $configs = [
     *     // A custom prefix prepended in the keys
     *     'prefix' => '',
     *     // The Redis host
     *     'host' => '127.0.0.1',
     *     // The Redis host port
     *     'port' => 6379,
     *     // The connection timeout
     *     'timeout' => 0.0,
     *     // Optional auth password
     *     'password' => null,
     *     // Optional database to select
     *     'database' => null,
     *     // Maximum attempts to try lock a session id
     *     'lock_attempts' => 60,
     *     // Interval between the lock attempts in microseconds
     *     'lock_sleep' => 1_000_000,
     *     // TTL to the lock (valid for the current session only)
     *     'lock_ttl' => 600,
     *     // The maxlifetime (TTL) used for cache item expiration
     *     'maxlifetime' => null, // Null to use the ini value of session.gc_maxlifetime
     *     // Match IP?
     *     'match_ip' => false,
     *     // Match User-Agent?
     *     'match_ua' => false,
     * ];
     * ```
     */
    protected function prepareConfig(#[SensitiveParameter] array $config) : void
    {
        $this->config = \array_replace([
            'prefix' => '',
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 0.0,
            'password' => null,
            'database' => null,
            'lock_attempts' => 60,
            'lock_sleep' => 1_000_000,
            'lock_ttl' => 600,
            'maxlifetime' => null,
            'match_ip' => false,
            'match_ua' => false,
        ], $config);
    }

    public function setRedis(Redis $redis) : static
    {
        $this->redis = $redis;
        return $this;
    }

    public function getRedis() : ?Redis
    {
        return $this->redis ?? null;
    }

    /**
     * Get a key for Redis, using the optional
     * prefix, match IP and match User-Agent configs.
     *
     * @param string $id The session id
     *
     * @return string The final key
     */
    protected function getKey(string $id) : string
    {
        return $this->config['prefix'] . $id . $this->getKeySuffix();
    }

    public function open($path, $name) : bool
    {
        if (isset($this->redis)) {
            return true;
        }
        $this->redis = new Redis();
        try {
            $this->redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout']
            );
        } catch (RedisException) {
            $this->log(
                'Session (redis): Could not connect to server '
                . $this->config['host'] . ':' . $this->config['port']
            );
            return false;
        }
        if (isset($this->config['password'])) {
            try {
                $this->redis->auth($this->config['password']);
            } catch (RedisException) {
                $this->log('Session (redis): Authentication failed');
                return false;
            }
        }
        if (isset($this->config['database'])
            && ! $this->redis->select($this->config['database'])
        ) {
            $this->log(
                "Session (redis): Could not select the database '{$this->config['database']}'"
            );
            return false;
        }
        return true;
    }

    public function read($id) : string
    {
        if ( ! isset($this->redis) || ! $this->lock($id)) {
            return '';
        }
        if ( ! isset($this->sessionId)) {
            $this->sessionId = $id;
        }
        $data = $this->redis->get($this->getKey($id));
        \is_string($data) ? $this->sessionExists = true : $data = '';
        $this->setFingerprint($data);
        return $data;
    }

    public function write($id, $data) : bool
    {
        if ( ! isset($this->redis)) {
            return false;
        }
        if ($id !== $this->sessionId) {
            if ( ! $this->unlock() || ! $this->lock($id)) {
                return false;
            }
            $this->sessionExists = false;
            $this->sessionId = $id;
        }
        if ($this->lockId === false) {
            return false;
        }
        $maxlifetime = $this->getMaxlifetime();
        $this->redis->expire($this->lockId, $this->config['lock_ttl']);
        if ($this->sessionExists === false || ! $this->hasSameFingerprint($data)) {
            if ($this->redis->set($this->getKey($id), $data, $maxlifetime)) {
                $this->setFingerprint($data);
                $this->sessionExists = true;
                return true;
            }
            return false;
        }
        return $this->redis->expire($this->getKey($id), $maxlifetime);
    }

    public function updateTimestamp($id, $data) : bool
    {
        return $this->redis->setex($this->getKey($id), $this->getMaxlifetime(), $data);
    }

    public function close() : bool
    {
        if ( ! isset($this->redis)) {
            return true;
        }
        try {
            if ($this->redis->ping()) {
                if ($this->lockId) {
                    $this->redis->del($this->lockId);
                }
                if ( ! $this->redis->close()) {
                    return false;
                }
            }
        } catch (RedisException $e) {
            $this->log('Session (redis): Got RedisException on close: ' . $e->getMessage());
        }
        $this->redis = null;
        return true;
    }

    public function destroy($id) : bool
    {
        if ( ! $this->lockId) {
            return false;
        }
        $result = $this->redis->del($this->getKey($id));
        if ($result !== 1) {
            $this->log(
                'Session (redis): Expected to delete 1 key, deleted ' . $result,
                LogLevel::DEBUG
            );
        }
        return true;
    }

    public function gc($max_lifetime) : int | false
    {
        return 0;
    }

    protected function lock(string $id) : bool
    {
        $ttl = $this->config['lock_ttl'];
        if ($this->lockId && $this->redis->get($this->lockId)) {
            return $this->redis->expire($this->lockId, $ttl);
        }
        $lockId = $this->getKey($id) . ':lock';
        $attempt = 0;
        while ($attempt < $this->config['lock_attempts']) {
            $attempt++;
            $oldTtl = $this->redis->ttl($lockId);
            if ($oldTtl > 0) {
                \usleep($this->config['lock_sleep']);
                continue;
            }
            if ( ! $this->redis->setex($lockId, $ttl, (string) \time())) {
                $this->log('Session (redis): Error while trying to lock ' . $lockId);
                return false;
            }
            $this->lockId = $lockId;
            break;
        }
        if ($attempt === $this->config['lock_attempts']) {
            $this->log(
                "Session (redis): Unable to lock {$lockId} after {$attempt} attempts"
            );
            return false;
        }
        if (isset($oldTtl) && $oldTtl === -1) {
            $this->log(
                'Session (redis): Lock for ' . $this->getKey($id) . ' had not TTL',
                LogLevel::DEBUG
            );
        }
        return true;
    }

    protected function unlock() : bool
    {
        if ($this->lockId === false) {
            return true;
        }
        if ( ! $this->redis->del($this->lockId)) {
            $this->log('Session (redis): Error while trying to unlock ' . $this->lockId);
            return false;
        }
        $this->lockId = false;
        return true;
    }
}
