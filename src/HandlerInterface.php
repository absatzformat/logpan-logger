<?php

declare(strict_types=1);

namespace LogPan\Logger;

interface HandlerInterface
{
	/**
	 * @param array<string, mixed> $record
	 */
	public function handle(array $record): void;
}