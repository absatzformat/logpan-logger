<?php

declare(strict_types=1);

namespace Logjar\Logger;

use JsonSerializable;

final class Record implements JsonSerializable
{
	/** @var string */
	protected $level;

	/** @var string */
	protected $message;

	/** @var int */
	protected $timestamp;

	public function __construct(string $level, string $message, int $timestamp)
	{
		$this->level = $level;
		$this->message = $message;
		$this->timestamp = $timestamp;
	}

	public function getLevel(): string
	{
		return $this->level;
	}

	public function getMessage(): string
	{
		return $this->message;
	}

	public function getTimestamp(): int
	{
		return $this->timestamp;
	}

	public function jsonSerialize()
	{
		return [
			'level' => $this->level,
			'message' => $this->message,
			'timestamp' => $this->timestamp
		];
	}
}
