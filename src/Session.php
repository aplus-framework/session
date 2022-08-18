<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework Session Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\Session;

use Framework\Session\Debug\SessionCollector;
use JetBrains\PhpStorm\Pure;
use LogicException;
use RuntimeException;

/**
 * Class Session.
 *
 * @package session
 */
class Session
{
    /**
     * @var array<string,mixed>
     */
    protected array $options = [];
    protected SaveHandler $saveHandler;
    protected SessionCollector $debugCollector;

    /**
     * Session constructor.
     *
     * @param array<string,int|string> $options
     * @param SaveHandler|null $handler
     */
    public function __construct(array $options = [], SaveHandler $handler = null)
    {
        $this->setOptions($options);
        if ($handler) {
            $this->saveHandler = $handler;
            \session_set_save_handler($handler);
        }
    }

    public function __destruct()
    {
        $this->stop();
    }

    public function __get(string $key) : mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value) : void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key) : bool
    {
        return $this->has($key);
    }

    public function __unset(string $key) : void
    {
        $this->remove($key);
    }

    /**
     * @see http://php.net/manual/en/session.security.ini.php
     *
     * @param array<string,int|string> $custom
     */
    protected function setOptions(array $custom) : void
    {
        $serializer = \ini_get('session.serialize_handler');
        $serializer = $serializer === 'php' ? 'php_serialize' : $serializer;
        $secure = (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        $default = [
            'name' => 'session_id',
            'serialize_handler' => $serializer,
            'sid_bits_per_character' => 6,
            'sid_length' => 48,
            'cookie_domain' => '',
            'cookie_httponly' => 1,
            'cookie_lifetime' => 7200,
            'cookie_path' => '/',
            'cookie_samesite' => 'Strict',
            'cookie_secure' => $secure,
            'referer_check' => '',
            'use_cookies' => 1,
            'use_only_cookies' => 1,
            'use_strict_mode' => 1,
            'use_trans_sid' => 0,
            // used to auto-regenerate the session id:
            'auto_regenerate_maxlifetime' => 0,
            'auto_regenerate_destroy' => true,
        ];
        $this->options = $custom
            ? \array_replace($default, $custom)
            : $default;
    }

    /**
     * @param array<string,mixed> $custom
     *
     * @return array<string,mixed>
     */
    protected function getOptions(array $custom = []) : array
    {
        $options = $custom
            ? \array_replace($this->options, $custom)
            : $this->options;
        unset(
            $options['auto_regenerate_maxlifetime'],
            $options['auto_regenerate_destroy']
        );
        return $options;
    }

    /**
     * @param array<string,int|string> $customOptions
     *
     * @throws LogicException if session was already active
     * @throws RuntimeException if session could not be started
     *
     * @return bool
     */
    public function start(array $customOptions = []) : bool
    {
        if ($this->isActive()) {
            throw new LogicException('Session was already active');
        }
        if ( ! @\session_start($this->getOptions($customOptions))) {
            throw new RuntimeException('Session could not be started');
        }
        $time = \time();
        $this->autoRegenerate($time);
        $this->clearTemp($time);
        $this->clearFlash();
        return true;
    }

    /**
     * Make sure the session is active.
     *
     * If it is not active, it will start it.
     *
     * @throws RuntimeException if session could not be started
     *
     * @return bool
     */
    public function activate() : bool
    {
        if ($this->isActive()) {
            return true;
        }
        return $this->start();
    }

    /**
     * Auto regenerate the session id.
     *
     * @param int $time
     *
     * @see https://owasp.org/www-community/attacks/Session_fixation
     */
    protected function autoRegenerate(int $time) : void
    {
        $maxlifetime = (int) $this->options['auto_regenerate_maxlifetime'];
        $isActive = $maxlifetime > 0;
        if (($isActive && empty($_SESSION['$']['regenerated_at']))
            || ($isActive && $_SESSION['$']['regenerated_at'] < ($time - $maxlifetime))
        ) {
            $this->regenerateId((bool) $this->options['auto_regenerate_destroy']);
        }
    }

    /**
     * Clears the Flash Data.
     */
    protected function clearFlash() : void
    {
        unset($_SESSION['$']['flash']['old']);
        if (isset($_SESSION['$']['flash']['new'])) {
            foreach ($_SESSION['$']['flash']['new'] as $key => $value) {
                $_SESSION['$']['flash']['old'][$key] = $value;
            }
        }
        unset($_SESSION['$']['flash']['new']);
        if (empty($_SESSION['$']['flash'])) {
            unset($_SESSION['$']['flash']);
        }
    }

    /**
     * Clears the Temp Data.
     *
     * @param int $time The max time to temp data survive
     */
    protected function clearTemp(int $time) : void
    {
        if (isset($_SESSION['$']['temp'])) {
            foreach ($_SESSION['$']['temp'] as $key => $value) {
                if ($value['ttl'] < $time) {
                    unset($_SESSION['$']['temp'][$key]);
                }
            }
        }
        if (empty($_SESSION['$']['temp'])) {
            unset($_SESSION['$']['temp']);
        }
    }

    /**
     * Tells if sessions are enabled, and one exists.
     *
     * @return bool
     */
    public function isActive() : bool
    {
        return \session_status() === \PHP_SESSION_ACTIVE;
    }

    /**
     * Destroys all data registered to a session.
     *
     * @return bool true on success or false on failure
     */
    public function destroy() : bool
    {
        if ($this->isActive()) {
            $destroyed = \session_destroy();
        }
        unset($_SESSION);
        return $destroyed ?? true;
    }

    /**
     * Sets a Cookie with the session name to be destroyed in the user-agent.
     *
     * @throws RuntimeException If it could not get the session name
     *
     * @return bool True if the Set-Cookie header was set to invalidate the
     * session cookie, false if output exists
     */
    public function destroyCookie() : bool
    {
        $name = \session_name();
        if ($name === false) {
            throw new RuntimeException('Could not get the session name');
        }
        $params = \session_get_cookie_params();
        return \setcookie($name, '', [
            'expires' => 0,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    /**
     * Write session data and end session.
     *
     * @return bool returns true on success or false on failure
     */
    public function stop() : bool
    {
        if ($this->isActive()) {
            $closed = \session_write_close();
        }
        return $closed ?? true;
    }

    /**
     * Discard session data changes and end session.
     *
     * @return bool returns true on success or false on failure
     */
    public function abort() : bool
    {
        if ($this->isActive()) {
            $aborted = \session_abort();
        }
        return $aborted ?? true;
    }

    /**
     * Tells if the session has an item.
     *
     * @param string $key The item key name
     *
     * @return bool True if it has, otherwise false
     */
    #[Pure]
    public function has(string $key) : bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Gets one session item.
     *
     * @param string $key The item key name
     *
     * @return mixed The item value or null if no set
     */
    #[Pure]
    public function get(string $key) : mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Get all session items.
     *
     * @return array<mixed> The value of the $_SESSION global
     */
    #[Pure]
    public function getAll() : array
    {
        return $_SESSION;
    }

    /**
     * Get multiple session items.
     *
     * @param array<string> $keys An array of key item names
     *
     * @return array<string,mixed> An associative array with items keys and
     * values. Item not set will return as null.
     */
    #[Pure]
    public function getMulti(array $keys) : array
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->get($key);
        }
        return $items;
    }

    /**
     * Set a session item.
     *
     * @param string $key The item key name
     * @param mixed $value The item value
     *
     * @rerun static
     */
    public function set(string $key, mixed $value) : static
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    /**
     * Set multiple session items.
     *
     * @param array<string,mixed> $items An associative array of items keys and
     * values
     *
     * @rerun static
     */
    public function setMulti(array $items) : static
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Remove (unset) a session item.
     *
     * @param string $key The item key name
     *
     * @rerun static
     */
    public function remove(string $key) : static
    {
        unset($_SESSION[$key]);
        return $this;
    }

    /**
     * Remove (unset) multiple session items.
     *
     * @param array<string> $keys A list of items keys names
     *
     * @rerun static
     */
    public function removeMulti(array $keys) : static
    {
        foreach ($keys as $key) {
            $this->remove($key);
        }
        return $this;
    }

    /**
     * Remove (unset) all session items.
     *
     * @rerun static
     */
    public function removeAll() : static
    {
        @\session_unset();
        $_SESSION = [];
        return $this;
    }

    /**
     * Update the current session id with a newly generated one.
     *
     * @param bool $deleteOldSession Whether to delete the old associated session item or not
     *
     * @return bool
     */
    public function regenerateId(bool $deleteOldSession = false) : bool
    {
        $regenerated = \session_regenerate_id($deleteOldSession);
        if ($regenerated) {
            $_SESSION['$']['regenerated_at'] = \time();
        }
        return $regenerated;
    }

    /**
     * Re-initialize session array with original values.
     *
     * @return bool true if the session was successfully reinitialized or false on failure
     */
    public function reset() : bool
    {
        return \session_reset();
    }

    /**
     * Get a Flash Data item.
     *
     * @param string $key The Flash item key name
     *
     * @return mixed The item value or null if not exists
     */
    #[Pure]
    public function getFlash(string $key) : mixed
    {
        return $_SESSION['$']['flash']['new'][$key]
            ?? $_SESSION['$']['flash']['old'][$key]
            ?? null;
    }

    /**
     * Set a Flash Data item, available only in the next time the session is started.
     *
     * @param string $key The Flash Data item key name
     * @param mixed $value The item value
     *
     * @rerun static
     */
    public function setFlash(string $key, mixed $value) : static
    {
        $_SESSION['$']['flash']['new'][$key] = $value;
        return $this;
    }

    /**
     * Remove a Flash Data item.
     *
     * @param string $key The item key name
     *
     * @rerun static
     */
    public function removeFlash(string $key) : static
    {
        unset(
            $_SESSION['$']['flash']['old'][$key],
            $_SESSION['$']['flash']['new'][$key]
        );
        return $this;
    }

    /**
     * Get a Temp Data item.
     *
     * @param string $key The item key name
     *
     * @return mixed The item value or null if it is expired or not set
     */
    public function getTemp(string $key) : mixed
    {
        if (isset($_SESSION['$']['temp'][$key])) {
            if ($_SESSION['$']['temp'][$key]['ttl'] > \time()) {
                return $_SESSION['$']['temp'][$key]['data'];
            }
            unset($_SESSION['$']['temp'][$key]);
        }
        return null;
    }

    /**
     * Set a Temp Data item.
     *
     * @param string $key The item key name
     * @param mixed $value The item value
     * @param int $ttl The Time-To-Live of the item, in seconds
     *
     * @rerun static
     */
    public function setTemp(string $key, mixed $value, int $ttl = 60) : static
    {
        $_SESSION['$']['temp'][$key] = [
            'ttl' => \time() + $ttl,
            'data' => $value,
        ];
        return $this;
    }

    /**
     * Remove (unset) a Temp Data item.
     *
     * @param string $key The item key name
     *
     * @rerun static
     */
    public function removeTemp(string $key) : static
    {
        unset($_SESSION['$']['temp'][$key]);
        return $this;
    }

    /**
     * Get/Set the session id.
     *
     * @param string|null $newId [optional] The new session id
     *
     * @throws LogicException when trying to set a new id and the session is active
     *
     * @return false|string The old session id or false on failure. Note: If a
     * $newId is set, it is accepted but not validated. When session_start is
     * called, the id is only used if it is valid
     */
    public function id(string $newId = null) : string | false
    {
        if ($newId !== null && $this->isActive()) {
            throw new LogicException(
                'Session ID cannot be changed when a session is active'
            );
        }
        return \session_id($newId);
    }

    /**
     * Perform session data garbage collection.
     *
     * @return false|int Returns the number of deleted session data for success,
     * false for failure
     */
    public function gc() : int | false
    {
        return @\session_gc();
    }

    public function setDebugCollector(SessionCollector $collector) : static
    {
        $this->debugCollector = $collector;
        $this->debugCollector->setSession($this)->setOptions($this->options);
        if (isset($this->saveHandler)) {
            $this->debugCollector->setSaveHandler($this->saveHandler);
        }
        return $this;
    }
}
