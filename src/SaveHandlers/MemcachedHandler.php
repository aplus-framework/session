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
use Memcached;
use OutOfBoundsException;
use SensitiveParameter;

/**
 * Class MemcachedHandler.
 *
 * @package session
 */
class MemcachedHandler extends SaveHandler
{
    protected ?Memcached $memcached;

    /**
     * Prepare configurations to be used by the MemcachedHandler.
     *
     * @param array<string,mixed> $config Custom configs
     *
     * The custom configs are:
     *
     * ```php
     * $configs = [
     *     // A custom prefix prepended in the keys
     *     'prefix' => '',
     *     // A list of Memcached servers
     *     'servers' => [
     *         [
     *             'host' => '127.0.0.1', // host always is required
     *             'port' => 11211, // port is optional, default to 11211
     *             'weight' => 0, // weight is optional, default to 0
     *         ],
     *     ],
     *     // An associative array of Memcached::OPT_* constants
     *     'options' => [
     *         Memcached::OPT_BINARY_PROTOCOL => true,
     *     ],
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
        $this->config = \array_replace_recursive([
            'prefix' => '',
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 0,
                ],
            ],
            'options' => [
                Memcached::OPT_BINARY_PROTOCOL => true,
            ],
            'lock_attempts' => 60,
            'lock_sleep' => 1_000_000,
            'lock_ttl' => 600,
            'maxlifetime' => null,
            'match_ip' => false,
            'match_ua' => false,
        ], $config);
        foreach ($this->config['servers'] as $index => $server) {
            if ( ! isset($server['host'])) {
                throw new OutOfBoundsException(
                    "Memcached host not set on server config '{$index}'"
                );
            }
        }
    }

    public function setMemcached(Memcached $memcached) : static
    {
        $this->memcached = $memcached;
        return $this;
    }

    public function getMemcached() : ?Memcached
    {
        return $this->memcached ?? null;
    }

    /**
     * Get expiration as a timestamp.
     *
     * Useful for Time To Live greater than a month (`60*60*24*30`).
     *
     * @param int $seconds
     *
     * @see https://www.php.net/manual/en/memcached.expiration.php
     *
     * @return int
     */
    protected function getExpiration(int $seconds) : int
    {
        return \time() + $seconds;
    }

    /**
     * Get a key for Memcached, using the optional
     * prefix, match IP and match User-Agent configs.
     *
     * NOTE: The max key length allowed by Memcached is 250 bytes.
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
        if (isset($this->memcached)) {
            return true;
        }
        $this->memcached = new Memcached();
        $pool = [];
        foreach ($this->config['servers'] as $server) {
            $host = $server['host'] . ':' . ($server['port'] ?? 11211);
            if (\in_array($host, $pool, true)) {
                $this->log(
                    'Session (memcached): Server pool already has ' . $host,
                    LogLevel::DEBUG
                );
                continue;
            }
            $result = $this->memcached->addServer(
                $server['host'],
                $server['port'] ?? 11211,
                $server['weight'] ?? 0,
            );
            if ($result === false) {
                $this->log("Session (memcached): Could not add {$host} to server pool");
                continue;
            }
            $pool[] = $host;
        }
        $result = $this->memcached->setOptions($this->config['options']);
        if ($result === false) {
            $this->log('Session (memcached): ' . $this->memcached->getLastErrorMessage());
        }
        if ( ! $this->memcached->getStats()) {
            $this->log('Session (memcached): Could not connect to any server');
            return false;
        }
        return true;
    }

    public function read($id) : string
    {
        if ( ! isset($this->memcached) || ! $this->lock($id)) {
            return '';
        }
        if ( ! isset($this->sessionId)) {
            $this->sessionId = $id;
        }
        $data = (string) $this->memcached->get($this->getKey($id));
        $this->setFingerprint($data);
        return $data;
    }

    public function write($id, $data) : bool
    {
        if ( ! isset($this->memcached)) {
            return false;
        }
        if ($id !== $this->sessionId) {
            if ( ! $this->unlock() || ! $this->lock($id)) {
                return false;
            }
            $this->setFingerprint('');
            $this->sessionId = $id;
        }
        if ($this->lockId === false) {
            return false;
        }
        $this->memcached->replace(
            $this->lockId,
            \time(),
            $this->getExpiration($this->config['lock_ttl'])
        );
        $maxlifetime = $this->getExpiration($this->getMaxlifetime());
        if ($this->hasSameFingerprint($data)) {
            return $this->memcached->touch($this->getKey($id), $maxlifetime);
        }
        if ($this->memcached->set($this->getKey($id), $data, $maxlifetime)) {
            $this->setFingerprint($data);
            return true;
        }
        return false;
    }

    public function updateTimestamp($id, $data) : bool
    {
        return $this->memcached->touch(
            $this->getKey($id),
            $this->getExpiration($this->getMaxlifetime())
        );
    }

    public function close() : bool
    {
        if ($this->lockId) {
            $this->memcached->delete($this->lockId);
        }
        if ( ! $this->memcached->quit()) {
            return false;
        }
        $this->memcached = null;
        return true;
    }

    public function destroy($id) : bool
    {
        if ( ! $this->lockId) {
            return false;
        }
        $destroyed = $this->memcached->delete($this->getKey($id));
        return ! ($destroyed === false
            && $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND);
    }

    public function gc($max_lifetime) : int | false
    {
        return 0;
    }

    protected function lock(string $id) : bool
    {
        $expiration = $this->getExpiration($this->config['lock_ttl']);
        if ($this->lockId && $this->memcached->get($this->lockId)) {
            return $this->memcached->replace($this->lockId, \time(), $expiration);
        }
        $lockId = $this->getKey($id) . ':lock';
        $attempt = 0;
        while ($attempt < $this->config['lock_attempts']) {
            $attempt++;
            if ($this->memcached->get($lockId)) {
                \usleep($this->config['lock_sleep']);
                continue;
            }
            if ( ! $this->memcached->set($lockId, \time(), $expiration)) {
                $this->log('Session (memcached): Error while trying to lock ' . $lockId);
                return false;
            }
            $this->lockId = $lockId;
            break;
        }
        if ($attempt === $this->config['lock_attempts']) {
            $this->log(
                "Session (memcached): Unable to lock {$lockId} after {$attempt} attempts"
            );
            return false;
        }
        return true;
    }

    protected function unlock() : bool
    {
        if ($this->lockId === false) {
            return true;
        }
        if ( ! $this->memcached->delete($this->lockId) &&
            $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND
        ) {
            $this->log('Session (memcached): Error while trying to unlock ' . $this->lockId);
            return false;
        }
        $this->lockId = false;
        return true;
    }
}
