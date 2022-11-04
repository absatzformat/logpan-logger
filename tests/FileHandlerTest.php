<?php

declare(strict_types=1);

use Logjar\Logger\Handler\FileHandler;
use Logjar\Logger\Handler\FormatHandler;
use Logjar\Logger\Handler\StreamHandler;
use Logjar\Logger\Logger;
use PHPUnit\Framework\TestCase;

final class FileHandlerTest extends TestCase
{
	public function testLog(): void
	{
		$this->markTestSkipped();
		
		$file = __DIR__ . '/log.txt';

		$fileHandler = new FileHandler($file);
		// $streamHandler = new StreamHandler($stream);
		$formatHandler = new FormatHandler();
		$logger = new Logger(new DateTimeZone('Europe/Berlin'));

		$logger->pushHandler($fileHandler);
		$logger->pushHandler($formatHandler);

		$message = 'Tweet';
		$logger->warning($message);

		$data = file_get_contents($file);

		$this->assertEquals($message, $data);
	}
}
