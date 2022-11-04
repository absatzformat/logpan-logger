<?php

declare(strict_types=1);

namespace Logjar\Logger\Handler;

use Logjar\Logger\HandlerInterface;
use Logjar\Logger\Record;

final class FormatHandler implements HandlerInterface
{
	public function handle(Record $record): void
	{
		$datetime = $record->getDateTime()->format('c');
		$level = strtoupper($record->getLevel());
		$message = $record->getMessage();

		$data = "[$datetime] [$level] $message\n";
		$record->formatted = $data;
	}
}
