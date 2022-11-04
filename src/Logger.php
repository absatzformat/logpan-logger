<?php

declare(strict_types=1);

namespace Logjar\Logger;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

use function is_string;
use function array_shift;
use function array_unshift;

class Logger extends AbstractLogger
{
	/** @var array<string, int> */
	protected static array $levels = [

		LogLevel::EMERGENCY => 0,
		LogLevel::ALERT => 1,
		LogLevel::CRITICAL => 2,
		LogLevel::ERROR => 3,
		LogLevel::WARNING => 4,
		LogLevel::NOTICE => 5,
		LogLevel::INFO => 6,
		LogLevel::DEBUG => 7
	];

	protected DateTimeZone $timezone;

	/** @var array{0: string, 1: HandlerInterface}[] */
	protected array $handler = [];

	/**
	 * @param iterable<mixed, HandlerInterface> $handler
	 */
	public function __construct(?DateTimeZone $timezone = null)
	{
		$this->timezone = $timezone ?? new DateTimeZone('UTC');
	}

	public function log($level, $message, array $context = []): void
	{
		if (!is_string($level) || !isset(self::$levels[$level])) {
			throw new InvalidArgumentException('Invalid log level < ' . (string)$level . ' > supplied');
		}

		$datetime = new DateTimeImmutable('now', $this->timezone);
		$record = new Record($level, $message, $context, $datetime);

		foreach ($this->getHandler() as $handlerLevel => $handler) {

			if (self::$levels[$handlerLevel] >= self::$levels[$level]) {
				$handler->handle($record);
			}

			if ($record->isPropagationStopped()) {
				break;
			}
		}
	}

	/**
	 * @return Generator<string, HandlerInterface>
	 */
	protected function getHandler(): Generator
	{
		foreach ($this->handler as $entry) {
			yield $entry[0] => $entry[1];
		}
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

	public function pushHandler(HandlerInterface $handler, string $level = LogLevel::DEBUG): self
	{
		if (!isset(self::$levels[$level])) {
			throw new InvalidArgumentException('Invalid log level "' . $level . '" supplied');
		}

		array_unshift($this->handler, [$level, $handler]);

		return $this;
	}

	public function popHandler(): ?array
	{
		return array_shift($this->handler);
	}
}
