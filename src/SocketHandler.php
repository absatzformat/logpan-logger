<?php

declare(strict_types=1);

namespace Logjar\Logger;

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
use const STREAM_CLIENT_PERSISTENT;

final class SocketHandler implements HandlerInterface
{
	/** @var false|resource */
	protected $socket;

	/** @var string */
	protected $host;

	/** @var int */
	protected $port;

	/** @var bool */
	protected $secure;

	/** @var string */
	protected $token;

	/** @var false|resource */
	protected $stream;

	/** @var int */
	protected $streamSize = 0;

	/** @var string */
	protected $target;

	/** @var string */
	protected $userAgent = 'LogjarLogger/1.0 (https://github.com/logjar/logger)';

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
		$this->socket = $this->createSocket('tcp://' . $this->host . ':' . $this->port);
	}

	public function __destruct()
	{
		$this->sendLogs();

		if (is_resource($this->socket)) {
			fclose($this->socket);
		}

		if (is_resource($this->stream)) {
			fclose($this->stream);
		}
	}

	public function handle(array $record): void
	{
		if ($this->stream === false) {
			return;
		}

		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$data = @json_encode($record, $flags);

		if ($data !== false) {

			$data .= "\r\n";

			$written = @fwrite($this->stream, $data);

			if ($written !== false) {
				$this->streamSize += $written;
			}
		}
	}

	protected function sendLogs(): void
	{
		if (
			$this->stream === false ||
			$this->socket === false ||
			$this->streamSize === 0
		) {
			return;
		}

		do {
			$enabled = $this->enableCrypto($this->socket, $this->secure);
		} while ($enabled === 0);

		$headers = $this->getRequestHeaders();

		$this->fwrite($this->socket, $headers);

		@rewind($this->stream);

		while (!@feof($this->stream)) {

			$bytes = @fread($this->stream, 1024);

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
		$headers .= "Connection: keep-alive\r\n";

		$headers .= "\r\n";

		return $headers;
	}

	/**
	 * @return false|resource
	 */
	protected function createSocket(string $remote)
	{
		$flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT;
		$socket = @stream_socket_client($remote, $errNo, $errMsg, 0, $flags);

		if ($socket !== false) {
			stream_set_blocking($socket, false);
		}

		return $socket;
	}

	/**
	 * @param resource $socket
	 * @return bool|int
	 */
	protected function enableCrypto($socket, bool $switch = true)
	{
		return @stream_socket_enable_crypto($socket, $switch, STREAM_CRYPTO_METHOD_ANY_CLIENT);
	}

	/**
	 * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
	 * @param resource $stream
	 * @return false|int
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
