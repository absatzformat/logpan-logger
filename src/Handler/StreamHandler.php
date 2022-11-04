<?php

declare(strict_types=1);

namespace Logjar\Logger\Handler;

use Logjar\Logger\HandlerInterface;
use Logjar\Logger\Record;
use RuntimeException;

final class StreamHandler implements HandlerInterface
{
	/** @var resource */
	protected $stream;

	protected ?int $chunkSize;

	protected bool $locking;

	/**
	 * @param resource $stream
	 */
	public function __construct($stream, ?int $chunkSize = null, bool $locking = false)
	{
		$this->stream = $stream;
		$this->chunkSize = $chunkSize;
		$this->locking = $locking;
	}

	public function handle(Record $record): void
	{
		$data = $record->formatted;
		$length = strlen($data);

		if ($this->locking) {
			@flock($this->stream, LOCK_EX);
		}

		do {
			$written = @fwrite($this->stream, $data, $this->chunkSize);

			if ($written === false) {
				throw new RuntimeException('Unable to write to stream');
			}

			$length -= $written;
		} while ($length > 0);

		if ($this->locking) {
			@flock($this->stream, LOCK_UN);
		}
	}
}
