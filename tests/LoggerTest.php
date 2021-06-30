<?php

declare(strict_types=1);

use LogPan\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
	public function testSendLogs(): void
	{
		$logger = new Logger('localhost:8080', 3, '1234', '/channel', false);
		$logger->alert('Line5');
		sleep(1);
		$logger->debug('Line6');
	}
}
