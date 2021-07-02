<?php

declare(strict_types=1);

namespace LogPan\Logger;

use function parse_url;
use function rtrim;
use function fopen;
use function is_resource;
use function fclose;
use function fwrite;
use function json_encode;
use function rewind;
use function feof;
use function fread;
use function fgets;
use function strlen;
use function stream_select;
use function stream_socket_client;
use function stream_socket_enable_crypto;

use const JSON_UNESCAPED_UNICODE;
use const JSON_UNESCAPED_SLASHES;
use const JSON_THROW_ON_ERROR;
use const STREAM_CLIENT_CONNECT;
use const STREAM_CLIENT_ASYNC_CONNECT;
use const STREAM_CRYPTO_METHOD_ANY_CLIENT;

final class SocketHandler implements HandlerInterface
{
	/** @var resource */
	protected $socket;

	/** @var string */
	protected $host;

	/** @var int */
	protected $port;

	/** @var bool */
	protected $secure;

	/** @var string */
	protected $token;

	/** @var resource */
	protected $stream;

	/** @var int */
	protected $streamSize;

	/** @var string */
	protected $target;

	/** @var string */
	protected $userAgent = 'LogPanLogger/1.0 (https://github.com/absatzformat/logpan-logger)';

	public function __construct(
		string $address,
		int $channel,
		string $token,
		string $path = '/channel'
	) {
		$url = parse_url($address);

		$this->host = $url['host'] ?? '';
		$this->secure = isset($url['scheme']) && $url['scheme'] === 'https';
		$this->port = $url['port'] ?? ($this->secure ? 443 : 80);

		$this->target = rtrim($path, '/') . '/' . $channel;
		$this->token = $token;

		$this->stream = @fopen('php://temp', 'r+');
		$this->streamSize = 0;
		$this->socket = $this->createSocket('tcp://' . $this->host . ':' . $this->port);
	}

	public function __destruct()
	{
		$this->sendLogs();

		/** @var false|resource $this->socket */
		if (is_resource($this->socket)) {
			fclose($this->socket);
		}

		/** @var false|resource $this->stream */
		if (is_resource($this->stream)) {
			fclose($this->stream);
		}
	}

	public function handle(array $record): void
	{
		$data = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$data .= "\r\n";

		$written = @fwrite($this->stream, $data);

		if ($written !== false) {
			$this->streamSize += $written;
		}
	}

	protected function sendLogs(): void
	{
		if ($this->streamSize === 0) {
			return;
		}

		if ($this->secure) {

			do {
				$enabled = $this->enableCrypto($this->socket);
			} while ($enabled === 0);
		}

		$headers = $this->getRequestHeaders();

		$this->fwrite($this->socket, $headers);

		@rewind($this->stream);

		while (!@feof($this->stream)) {

			$bytes = @fread($this->stream, 4096);

			$this->fwrite($this->socket, $bytes);
		}
	}

	protected function getRequestHeaders(): string
	{
		$headers = "POST {$this->target} HTTP/1.1\r\n";
		$headers .= "Host: {$this->host}\r\n";
		$headers .= "User-Agent: {$this->userAgent}\r\n";
		$headers .= "Authorization: Bearer {$this->token}\r\n";
		$headers .= "Content-Type: text/plain\r\n";
		$headers .= "Content-Length: {$this->streamSize}\r\n";
		$headers .= "Connection: close\r\n";

		$headers .= "\r\n";

		return $headers;
	}

	/**
	 * @return resource
	 */
	protected function createSocket(string $remote)
	{
		$errNo = null;
		$errMsg = null;

		$socket = @stream_socket_client($remote, $errNo, $errMsg, 1, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

		return $socket;
	}

	/**
	 * @param resource $socket
	 * @return bool|int
	 */
	protected function enableCrypto($socket)
	{
		return @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
	}

	/**
	 * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
	 * @param resource $stream
	 * @return bool|int
	 */
	protected function fwrite($stream, string $bytes)
	{
		if (!strlen($bytes)) {
			return 0;
		}

		$result = @fwrite($stream, $bytes);

		if ($result !== 0) {
			return $result;
		}

		$read = [];
		$write = [$stream];
		$except = [];
		@stream_select($read, $write, $except, 0);

		if (!$write) {
			return 0;
		}

		$result = @fwrite($stream, $bytes);

		if ($result !== 0) {
			return $result;
		}

		return false;
	}
}
