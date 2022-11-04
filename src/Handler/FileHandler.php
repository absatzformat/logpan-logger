<?php

declare(strict_types=1);

namespace Logjar\Logger\Handler;

use Logjar\Logger\HandlerInterface;
use Logjar\Logger\Record;
use RuntimeException;

final class FileHandler implements HandlerInterface
{
	protected string $file;

	protected ?int $permissions;

	protected bool $locking;

	protected ?StreamHandler $streamHandler = null;

	public function __construct(string $file, ?int $permissions = null, bool $locking = false)
	{
		$this->file = $file;
		$this->permissions = $permissions;
		$this->locking = $locking;
	}

	public function handle(Record $record): void
	{
		$streamHandler = $this->getStreamHandler();
		$streamHandler->handle($record);
	}

	protected function getStreamHandler(): StreamHandler
	{
		if ($this->streamHandler === null) {

			$stream = @fopen($this->file, 'a');

			if (!is_resource($stream)) {
				throw new RuntimeException('Unable to create stream for file "' . $this->file . '"');
			}

			if ($this->permissions !== null) {
				@chmod($this->file, $this->permissions);
			}

			$this->streamHandler = new StreamHandler($stream, null, $this->locking);
		}

		return $this->streamHandler;
	}
}
