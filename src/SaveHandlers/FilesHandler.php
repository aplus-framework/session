<?php namespace Framework\Session\SaveHandlers;

use Framework\Log\Logger;
use Framework\Session\SaveHandler;
use LogicException;
use RuntimeException;

/**
 * Class FilesHandler.
 */
class FilesHandler extends SaveHandler
{
	/**
	 * @var resource|null
	 */
	protected $stream;

	/**
	 * Prepare configurations to be used by the FilesHandler.
	 *
	 * @param array<string,mixed> $config Custom configs
	 *
	 * The custom configs are:
	 *
	 * ```php
	 * $configs = [
	 *     // The directory path where the session files will be saved
	 *     'directory' => '',
	 *     // A custom directory name inside the `directory` path
	 *     'prefix' => '',
	 *     // Match IP?
	 *     'match_ip' => false,
	 *     // Match User-Agent?
	 *     'match_ua' => false,
	 * ];
	 * ```
	 */
	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => '',
			'directory' => '',
			'match_ip' => false,
			'match_ua' => false,
		], $config);
		if (empty($this->config['directory'])) {
			throw new LogicException('Session config has not a directory');
		}
		$this->config['directory'] = \rtrim(
			$this->config['directory'],
			\DIRECTORY_SEPARATOR
		) . \DIRECTORY_SEPARATOR;
		if ( ! \is_dir($this->config['directory'])) {
			throw new LogicException(
				'Session config directory does not exist: ' . $this->config['directory']
			);
		}
		if ($this->config['prefix']) {
			$dirname = $this->config['directory'] . $this->config['prefix'] . \DIRECTORY_SEPARATOR;
			if ( ! \is_dir($dirname) && ! \mkdir($dirname, 0700) && ! \is_dir($dirname)) {
				throw new RuntimeException(
					"Session prefix directory '{$dirname}' was not created",
				);
			}
			$this->config['directory'] = $dirname;
		}
	}

	/**
	 * Get the filename, using the optional
	 * match IP and match User-Agent configs.
	 *
	 * @param string $id The session id
	 *
	 * @return string The final filename
	 */
	protected function getFilename(string $id) : string
	{
		$filename = $this->config['directory'] . $id[0] . $id[1] . \DIRECTORY_SEPARATOR . $id;
		if ($this->config['match_ip']) {
			$filename .= ':' . $this->getIP();
		}
		if ($this->config['match_ua']) {
			$filename .= ':' . \md5($this->getUA());
		}
		return $filename;
	}

	public function open($path, $name) : bool
	{
		return true;
	}

	public function read($id) : string
	{
		if ($this->stream !== null) {
			\rewind($this->stream);
			return $this->readData();
		}
		$filename = $this->getFilename($id);
		$dirname = \dirname($filename);
		if ( ! \is_dir($dirname) && ! \mkdir($dirname, 0700) && ! \is_dir($dirname)) {
			throw new RuntimeException(
				"Session subdirectory '{$dirname}' was not created",
			);
		}
		$this->sessionExists = \is_file($filename);
		if ( ! $this->lock($filename)) {
			return '';
		}
		if ( ! isset($this->sessionId)) {
			$this->sessionId = $id;
		}
		if ( ! $this->sessionExists) {
			\chmod($filename, 0600);
			$this->setFingerprint('');
			return '';
		}
		return $this->readData();
	}

	protected function readData() : string
	{
		$data = '';
		while ( ! \feof($this->stream)) {
			$data .= \fread($this->stream, 1024);
		}
		$this->setFingerprint($data);
		return $data;
	}

	public function write($id, $data) : bool
	{
		if ( ! isset($this->stream)) {
			return false;
		}
		if ($id !== $this->sessionId) {
			$this->sessionId = $id;
		}
		if ($this->hasSameFingerprint($data)) {
			return ! $this->sessionExists || \touch($this->getFilename($id));
		}
		if ($this->sessionExists) {
			\ftruncate($this->stream, 0);
			\rewind($this->stream);
		}
		if ($data !== '') {
			$written = \fwrite($this->stream, $data);
			if ($written === false) {
				$this->log('Session (files): Unable to write data');
				return false;
			}
		}
		$this->setFingerprint($data);
		return true;
	}

	public function updateTimestamp($id, $data) : bool
	{
		return \touch($this->getFilename($id));
	}

	public function close() : bool
	{
		if ( ! \is_resource($this->stream)) {
			return true;
		}
		$this->unlock();
		$this->sessionExists = false;
		return true;
	}

	public function destroy($id) : bool
	{
		$this->close();
		\clearstatcache();
		$filename = $this->getFilename($id);
		return ! \is_file($filename) || \unlink($filename);
	}

	public function gc($max_lifetime) : int | false
	{
		$dir_handle = \opendir($this->config['directory']);
		if ($dir_handle === false) {
			$this->log(
				"Session (files): Garbage Collector could not open directory '{$this->config['directory']}'",
				Logger::DEBUG
			);
			return false;
		}
		$gc_count = 0;
		$max_lifetime = \time() - $max_lifetime;
		while (($filename = \readdir($dir_handle)) !== false) {
			if ($filename !== '.'
				&& $filename !== '..'
				&& \is_dir($this->config['directory'] . $filename)
			) {
				$gc_count += $this->gcSubdir(
					$this->config['directory'] . $filename,
					$max_lifetime
				);
			}
		}
		\closedir($dir_handle);
		return $gc_count;
	}

	protected function gcSubdir(string $directory, int $max_mtime) : int
	{
		$gc_count = 0;
		$dir_handle = \opendir($directory);
		if ($dir_handle === false) {
			return $gc_count;
		}
		while (($filename = \readdir($dir_handle)) !== false) {
			$filename = $directory . \DIRECTORY_SEPARATOR . $filename;
			if (\is_dir($filename)) {
				continue;
			}
			$mtime = \filemtime($filename);
			if (($mtime < $max_mtime) && \unlink($filename)) {
				$gc_count++;
			}
		}
		\closedir($dir_handle);
		if (\count((array) \scandir($directory)) === 2) {
			\rmdir($directory);
		}
		return $gc_count;
	}

	protected function lock(string $id) : bool
	{
		$stream = \fopen($id, 'c+b');
		if ($stream === false) {
			return false;
		}
		if (\flock($stream, \LOCK_EX) === false) {
			$this->log("Session (files): Error while trying to lock '{$id}'");
			\fclose($stream);
			return false;
		}
		$this->stream = $stream;
		return true;
	}

	protected function unlock() : bool
	{
		if ($this->stream === null) {
			return true;
		}
		$unlocked = \flock($this->stream, \LOCK_UN);
		if ($unlocked === false) {
			$this->log('Session (files): Error while trying to unlock ' . $this->getFilename($this->sessionId));
		}
		\fclose($this->stream);
		$this->stream = null;
		return true;
	}
}
