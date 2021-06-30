<?php

declare(strict_types=1);

use LogPan\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
	public function testSendLogs(): void
	{
		$logger = new Logger('localhost:8080', 2, '1234', '/', false);
		$logger->alert('Line1');
		sleep(1);
		$logger->alert('Line2');
	}
}
