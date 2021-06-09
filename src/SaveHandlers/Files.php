<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;
use LogicException;
use RuntimeException;

class Files extends SaveHandler
{
	/**
	 * @var resource|null
	 */
	protected $stream;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => '',
			'directory' => '',
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

	protected function getFilename(string $id) : string
	{
		return $this->config['directory'] . $id[0] . $id[1] . \DIRECTORY_SEPARATOR . $id;
	}

	public function open($path, $name) : bool
	{
		return true;
	}

	public function read($id) : string
	{
		if ($this->stream !== null) {
			\rewind($this->stream);
		} else {
			$filename = $this->getFilename($id);
			$dirname = \dirname($filename);
			if ( ! \is_dir($dirname) && ! \mkdir($dirname, 0700) && ! \is_dir($dirname)) {
				throw new RuntimeException(
					"Session subdirectory '{$dirname}' was not created",
				);
			}
			$this->sessionExists = \is_file($filename);
			if ( ! $this->getLock($filename)) {
				return '';
			}
			if ( ! isset($this->sessionId)) {
				$this->sessionId = $id;
			}
			if ( ! $this->sessionExists) {
				\chmod($filename, 0600);
				$this->fingerprint = \md5('');
				return '';
			}
		}
		$data = '';
		while ( ! \feof($this->stream)) {
			$data .= \fread($this->stream, 1024);
		}
		$this->fingerprint = \md5($data);
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
		if ($this->fingerprint === \md5($data)) {
			return ! $this->sessionExists || \touch($this->getFilename($id));
		}
		if ($this->sessionExists) {
			\ftruncate($this->stream, 0);
			\rewind($this->stream);
		}
		if ($data !== '') {
			\fwrite($this->stream, $data);
		}
		$this->fingerprint = \md5($data);
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
		$this->releaseLock();
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

	public function gc($max_lifetime) : bool
	{
		$dir_handle = \opendir($this->config['directory']);
		if ($dir_handle === false) {
			return false;
		}
		while (($filename = \readdir($dir_handle)) !== false) {
			if ($filename !== '.'
				&& $filename !== '..'
				&& \is_dir($this->config['directory'] . $filename)
			) {
				$this->gcSubdir($this->config['directory'] . $filename, $max_lifetime);
			}
		}
		\closedir($dir_handle);
		return true;
	}

	protected function gcSubdir(string $directory, int $max_lifetime) : void
	{
		$dir_handle = \opendir($directory);
		if ($dir_handle === false) {
			return;
		}
		$dir_count = 0;
		while (($filename = \readdir($dir_handle)) !== false) {
			$filename = $directory . \DIRECTORY_SEPARATOR . $filename;
			if (\is_dir($filename)) {
				$dir_count++;
				continue;
			}
			$mtime = \filemtime($filename);
			if ($mtime < \time() - $max_lifetime) {
				\unlink($filename);
			}
		}
		\closedir($dir_handle);
		if ($dir_count === 2) {
			\rmdir($directory);
		}
	}

	protected function getLock(string $id) : bool
	{
		$stream = \fopen($id, 'c+b');
		if ($stream === false) {
			return false;
		}
		if (\flock($stream, \LOCK_EX) === false) {
			\fclose($stream);
			return false;
		}
		$this->stream = $stream;
		return true;
	}

	protected function releaseLock() : bool
	{
		if ($this->stream === null) {
			return true;
		}
		\flock($this->stream, \LOCK_UN);
		\fclose($this->stream);
		$this->stream = null;
		return true;
	}
}
