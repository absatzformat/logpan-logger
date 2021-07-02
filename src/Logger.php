<?php

declare(strict_types=1);

namespace LogPan\Logger;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

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

	/** @var DateTimeZone */
	protected $timezone;

	/** @var HandlerInterface */
	protected $handler;

	public function __construct(HandlerInterface $handler, ?DateTimeZone $timezone = null)
	{
		$this->handler = $handler;
		$this->timezone = $timezone ?? new DateTimeZone('UTC');
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
		$datetime = new DateTimeImmutable('now', $this->timezone);

		$record = [
			'level' => (string)$level,
			'message' => $message,
			'timestamp' => (int)$datetime->format('U')
		];

		$this->handler->handle($record);
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

	/**
	 * @return string[]
	 */
	public static function getLevels(): array
	{
		return array_values(self::$logLevels);
	}

	public function getTimezone(): DateTimeZone
	{
		return $this->timezone;
	}

	public function setTimezone(DateTimeZone $timezone): self
	{
		$this->timezone = $timezone;

		return $this;
	}
}
