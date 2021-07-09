<?php

declare(strict_types=1);

namespace Logjar\Logger;

interface HandlerInterface
{
	public function handle(Record $record): void;
}
