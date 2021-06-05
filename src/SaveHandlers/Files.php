<?php namespace Framework\Session\SaveHandlers;

use Framework\Session\SaveHandler;

class Files extends SaveHandler
{
	/**
	 * @var resource|null
	 */
	protected $stream;

	protected function prepareConfig(array $config) : void
	{
		$this->config = \array_replace([
			'prefix' => 'session',
			'directory' => '/tmp/',
		], $config);
	}

	protected function getFilename(string $id) : string
	{
		$id .= $this->matchIP ? ':' . $this->getIP() : '';
		$id .= $this->matchUA ? ':' . $this->getUA() : '';
		$id = \md5($id);
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
		}
		$data = '';
		while ($data .= \fread($this->stream, 1024)) {
		}
		return $data;
	}

	public function write($id, $data) : bool
	{
		return \fwrite($this->stream, $data) !== false;
	}

	public function updateTimestamp($id, $data) : bool
	{
		return \touch($this->getFilename($id));
	}

	public function close() : bool
	{
		return \fclose($this->stream);
	}

	public function destroy($id) : bool
	{
		return \unlink($this->getFilename($id));
	}

	public function gc($max_lifetime) : bool
	{
		$dir_handle = \opendir($this->config['directory']);
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

	protected function gcSubdir(string $directory, int $max_lifetime)
	{
		$dir_handle = \opendir($directory);
		while (($filename = \readdir($dir_handle)) !== false) {
			if ($filename === '.'
				|| $filename === '..'
				|| \is_dir($directory . $filename)
			) {
				continue;
			}
			$mtime = \filemtime($directory . $filename);
			if ($mtime < \time() - $max_lifetime) {
				\unlink($directory . $filename);
			}
		}
		\closedir($dir_handle);
	}
}
