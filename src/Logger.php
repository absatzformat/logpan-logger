<?php

declare(strict_types=1);

namespace LogPan\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger
{
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

	public function __construct(string $url, int $channelId, )
	{
	}

	public function log($level, $message, array $context = []): void
	{
		if (!in_array($level, self::$logLevels)) {
			throw new InvalidArgumentException('Invalid log level supplied');
		}

		if (!is_string($message) && !(is_object($message) && method_exists($message, '__toString'))) {
			throw new InvalidArgumentException('Message must be a string or stringable object');
		}

		$message = $this->interpolateMessage((string)$message, $context);

		$data = [
			'tag' => (string)$level,
			'message' => $message
		];


	}

	protected function interpolateMessage(string $message, array $context): string
	{
		$replace = [];
		foreach ($context as $key => $value) {

			if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
				$replace['{' . $key . '}'] = (string)$value;
			}
		}

		return strtr($message, $replace);
	}
}
