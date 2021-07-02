<?php

declare(strict_types=1);

use LogPan\Logger\Logger;
use LogPan\Logger\SocketHandler;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
	public function testSendLogs(): void
	{
		$this->expectNotToPerformAssertions();

		$handler = new SocketHandler('https://logpan.absatzprojekt.de', 1, '1234', '/channel', true);
		$logger = new Logger($handler);
		$logger->alert('Line5');
		// sleep(1);
		$logger->debug('Line6');
	}

	public function testTimezone(): void
	{
		$handler = new SocketHandler('', 1, '');
		$logger = new Logger($handler);
		$timezone = $logger->getTimezone();
		$this->assertEquals('UTC', $timezone->getName());

		$logger->setTimezone(new DateTimeZone('Europe/Berlin'));
		$timezone = $logger->getTimezone();
		$this->assertEquals('Europe/Berlin', $timezone->getName());
	}

	public function testGetLevels(): void
	{
		$handler = new SocketHandler('', 1, '');
		$logger = new Logger($handler);
		$levels = $logger->getLevels();
		$expected = [
			'emergency',
			'alert',
			'critical',
			'error',
			'warning',
			'notice',
			'info',
			'debug'
		];
		$this->assertSame($expected, $levels);
	}
}
