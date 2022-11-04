<?php

declare(strict_types=1);

namespace Logjar\Logger;

use DateTimeImmutable;
use DateTimeInterface;

final class Record
{
	public string $formatted;

	/** @var array<string, mixed> */
	public array $misc = [];

	protected string $level;

	protected string $message;

	protected array $context;

	protected DateTimeImmutable $datetime;

	protected bool $propagationStopped = false;

	public function __construct(string $level, string $message, array $context, DateTimeImmutable $datetime)
	{
		$this->level = $level;
		$this->message = $message;
		$this->context = $context;
		$this->datetime = $datetime;

		$this->formatted = $message;
	}

	public function stopPropagation(): void
	{
		$this->propagationStopped = true;
	}

	public function isPropagationStopped(): bool
	{
		return $this->propagationStopped;
	}

	public function getLevel(): string
	{
		return $this->level;
	}

	public function getMessage(): string
	{
		return $this->message;
	}

	public function getContext(): array
	{
		return $this->context;
	}

	public function getDateTime(): DateTimeInterface
	{
		return $this->datetime;
	}
}
