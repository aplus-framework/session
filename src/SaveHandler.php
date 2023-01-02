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

use Framework\Log\Logger;
use Framework\Log\LogLevel;
use SensitiveParameter;

/**
 * Class SaveHandler.
 *
 * @see https://www.php.net/manual/en/class.sessionhandler.php
 * @see https://gist.github.com/mindplay-dk/623bdd50c1b4c0553cd3
 * @see https://www.cloudways.com/blog/setup-redis-as-session-handler-php/#sessionlifecycle
 *
 * @package session
 */
abstract class SaveHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    /**
     * The configurations used by the save handler.
     *
     * @var array<string,mixed>
     */
    protected array $config;
    /**
     * The current data fingerprint.
     *
     * @var string
     */
    protected string $fingerprint;
    /**
     * The lock id or false if is not locked.
     *
     * @var false|string
     */
    protected string | false $lockId = false;
    /**
     * Tells if the session exists (if was read).
     *
     * @var bool
     */
    protected bool $sessionExists = false;
    /**
     * The current session ID.
     *
     * @var string|null
     */
    protected ?string $sessionId;
    /**
     * The Logger instance or null if it was not set.
     *
     * @var Logger|null
     */
    protected ?Logger $logger;

    /**
     * SessionSaveHandler constructor.
     *
     * @param array<string,mixed> $config
     * @param Logger|null $logger
     */
    public function __construct(
        #[SensitiveParameter] array $config = [],
        Logger $logger = null
    ) {
        $this->prepareConfig($config);
        $this->logger = $logger;
    }

    /**
     * Prepare configurations to be used by the save handler.
     *
     * @param array<string,mixed> $config Custom configs
     *
     * @codeCoverageIgnore
     */
    protected function prepareConfig(#[SensitiveParameter] array $config) : void
    {
        $this->config = $config;
    }

    /**
     * @return array<string,mixed>
     */
    public function getConfig() : array
    {
        return $this->config;
    }

    /**
     * Log a message if the Logger is set.
     *
     * @param string $message The message to log
     * @param LogLevel $level The log level
     */
    protected function log(string $message, LogLevel $level = LogLevel::ERROR) : void
    {
        $this->logger?->log($level, $message);
    }

    /**
     * Set the data fingerprint.
     *
     * @param string $data The data to set the new fingerprint
     */
    protected function setFingerprint(string $data) : void
    {
        $this->fingerprint = $this->makeFingerprint($data);
    }

    /**
     * Make the fingerprint value.
     *
     * @param string $data The data to get the fingerprint
     *
     * @return string The fingerprint hash
     */
    private function makeFingerprint(string $data) : string
    {
        return \hash('xxh3', $data);
    }

    /**
     * Tells if the data has the same current fingerprint.
     *
     * @param string $data The data to compare
     *
     * @return bool True if the fingerprints are the same, otherwise false
     */
    protected function hasSameFingerprint(string $data) : bool
    {
        return $this->fingerprint === $this->makeFingerprint($data);
    }

    /**
     * Get the maxlifetime (TTL) used by cache handlers or locking.
     *
     * NOTE: It will use the `maxlifetime` config or the ini value of
     * `session.gc_maxlifetime` as fallback.
     *
     * @return int The maximum lifetime of a session in seconds
     */
    protected function getMaxlifetime() : int
    {
        return (int) ($this->config['maxlifetime'] ?? \ini_get('session.gc_maxlifetime'));
    }

    /**
     * Get the remote IP address.
     *
     * @return string
     */
    protected function getIP() : string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Get the HTTP User-Agent.
     *
     * @return string
     */
    protected function getUA() : string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    protected function getKeySuffix() : string
    {
        $suffix = '';
        if ($this->config['match_ip']) {
            $suffix .= ':' . $this->getIP();
        }
        if ($this->config['match_ua']) {
            $suffix .= ':' . $this->getUA();
        }
        if ($suffix) {
            $suffix = \hash('xxh3', $suffix);
        }
        return $suffix;
    }

    /**
     * Validate session id.
     *
     * @param string $id The session id
     *
     * @see https://www.php.net/manual/en/sessionupdatetimestamphandlerinterface.validateid.php
     *
     * @return bool Returns TRUE if the id is valid, otherwise FALSE
     */
    public function validateId($id) : bool
    {
        $bits = \ini_get('session.sid_bits_per_character') ?: 5;
        $length = \ini_get('session.sid_length') ?: 40;
        $bitsRegex = [
            4 => '[0-9a-f]',
            5 => '[0-9a-v]',
            6 => '[0-9a-zA-Z,-]',
        ];
        return isset($bitsRegex[$bits])
            && \preg_match('#\A' . $bitsRegex[$bits] . '{' . $length . '}\z#', $id);
    }

    /**
     * Initialize the session.
     *
     * @param string $path The path where to store/retrieve the session
     * @param string $name The session name
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.open.php
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract public function open($path, $name) : bool;

    /**
     * Read session data.
     *
     * @param string $id The session id to read data for
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.read.php
     *
     * @return string Returns an encoded string of the read data.
     * If nothing was read, it returns an empty string
     */
    abstract public function read($id) : string;

    /**
     * Write session data.
     *
     * @param string $id The session id
     * @param string $data The encoded session data. This data is the result
     * of the PHP internally encoding the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     *
     * NOTE: Sessions can use an alternative serialization method
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.write.php
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract public function write($id, $data) : bool;

    /**
     * Update the timestamp of a session.
     *
     * @param string $id The session id
     * @param string $data The encoded session data. This data is the result
     * of the PHP internally encoding the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     *
     * NOTE: Sessions can use an alternative serialization method
     *
     * @see https://www.php.net/manual/en/sessionupdatetimestamphandlerinterface.updatetimestamp.php
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract public function updateTimestamp($id, $data) : bool;

    /**
     * Close the session.
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.close.php
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract public function close() : bool;

    /**
     * Destroy a session.
     *
     * @param string $id The session ID being destroyed
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.destroy.php
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract public function destroy($id) : bool;

    /**
     * Cleanup old sessions.
     *
     * @param int $max_lifetime Sessions that have not updated for
     * the last $maxLifetime seconds will be removed
     *
     * @see https://www.php.net/manual/en/sessionhandlerinterface.gc.php
     *
     * @return false|int Returns the number of deleted session data for success,
     * false for failure
     */
    abstract public function gc($max_lifetime) : int | false;

    /**
     * Acquire a lock for a session id.
     *
     * @param string $id The session id
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract protected function lock(string $id) : bool;

    /**
     * Unlock the current session lock id.
     *
     * @return bool Returns TRUE on success, FALSE on failure
     */
    abstract protected function unlock() : bool;
}
