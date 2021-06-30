<?php

declare(strict_types=1);

namespace LogPan\Logger;

use DateTime;
use DateTimeZone;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use RuntimeException;

class Logger extends AbstractLogger
{
	/** @var string[] */
	protected static $logLevels = [

		LogLevel::EMERGENCY,
		LogLevel::ALERT,
		LogLevel::CRITICAL,
		LogLevel::ERROR,
		LogLevel::WARNING,
		LogLevel::NOTICE,
		LogLevel::INFO,
		LogLevel::DEBUG
	];

	/** @var array<int, string> */
	protected $logs = [];

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
	protected $streamSize = 0;

	/** @var string */
	protected $target;

	/** @var string */
	protected $userAgent = 'LogPanLogger/1.0 (https://github.com/absatzformat/logpan-logger)';

	public function __construct(
		string $address,
		int $channel,
		string $token,
		string $path = '/channel',
		bool $secure = true
	) {
		$url = parse_url($address);

		$this->host = $url['host'] ?? '';
		$this->port = $url['port'] ?? (isset($url['scheme']) && $url['scheme'] === 'https' ? 443 : 80);
		$this->secure = $secure;

		$this->target = rtrim($path, '/') . '/' . $channel;
		$this->token = $token;

		$this->stream = fopen('php://temp', 'r+');
		$this->socket = $this->createSocket('tcp://' . $this->host . ':' . $this->port, $this->secure);
	}

	public function __destruct()
	{
		$this->sendLogs();

		fclose($this->socket);
		fclose($this->stream);
	}

	public function log($level, $message, array $context = []): void
	{
		if (!in_array($level, self::$logLevels)) {
			throw new InvalidArgumentException('Invalid log level supplied');
		}

		// if (!is_string($message) && !(is_object($message) && method_exists($message, '__toString'))) {
		// 	throw new InvalidArgumentException('Message must be a string or stringable object');
		// }

		$message = $this->interpolateMessage($message, $context);

		$log = [
			'level' => (string)$level,
			'message' => $message,
			'datetime' => (new DateTime('now', new DateTimeZone('UTC')))->format('c')
		];

		$data = json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		$data .= "\r\n";

		if (false !== $written = fwrite($this->stream, $data)) {
			$this->streamSize += $written;
		}
	}

	protected function interpolateMessage(string $message, array $context): string
	{
		$replace = [];

		/** @var mixed */
		foreach ($context as $key => $value) {

			if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
				$replace['{' . (string)$key . '}'] = (string)$value;
			}
		}

		return strtr($message, $replace);
	}

	protected function sendLogs(): void
	{
		$headers = $this->getRequestHeaders();

		$this->fwrite($this->socket, $headers);

		rewind($this->stream);

		while (!feof($this->stream)) {

			$bytes = fread($this->stream, 1024);
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
	protected function createSocket(string $address, bool $secure = false)
	{
		$errNo = null;
		$errMsg = null;

		$socket = @stream_socket_client($address, $errNo, $errMsg, 3, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);

		if ($socket === false) {
			throw new RuntimeException($errMsg, $errNo);
		}

		if ($secure) {

			if (false === @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) {
				$error = error_get_last();
				throw new RuntimeException('Cannot enable tls: ' . (isset($error) ? $error['message'] : ''));
			}
		}

		return $socket;
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
