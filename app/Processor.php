<?php

namespace Zoon\PyroSpy;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Processor {

	private int $interval;
	private int $batchLimit;

	private int $tsStart = 0;
	private int $tsEnd;
	/**
	 * @var array<string, int>
	 */
	private array $results;

	private Sender $sender;


	public function __construct(int $interval, int $batchLimit, Sender $sender) {
		$this->interval = $interval;
		$this->sender = $sender;
		$this->init();
		$this->batchLimit = $batchLimit;
	}

	public function __destruct() {
		$this->sendResults(true);
	}

	private function init(): void {
		$this->results = [];
		$this->tsStart = time();
		$this->tsEnd = $this->tsStart + $this->interval;
	}

	public function process(): void {
		$sample = [];

		foreach (self::getLine() as $line) {
			$isEndOfTrace = $line === '';

			if (!$isEndOfTrace) {
				$sample[] = $line;
			}

			if ($isEndOfTrace && count($sample) > 0) {
				try {
					$samplePrepared = self::prepareSample($sample);
					self::checkSample($samplePrepared);
				} catch (Throwable $e) {
					echo $e->getMessage() . PHP_EOL;
					var_dump($sample);
					$sample = [];
					continue;
				}

				$key = self::stringifyTrace($samplePrepared);
				$this->groupTrace($key);
				$this->sendResults();

				$sample = [];
			}
		}
	}

	/**
	 * @param list<string> $sample
	 * @return array<int|string, array{0:string, 1:string}>
	 */
	private static function prepareSample(array $sample): array {
		$samplePrepared = [];
		foreach ($sample as $item) {
			$item = explode(' ', $item);
			if (count($item) !== 3) {
				throw new InvalidArgumentException('Invalid sample shape');
			}
			$key = array_shift($item);
			$samplePrepared[$key] = $item;
		}
		return $samplePrepared;
	}

	/**
	 * @param array<int|string, array{0:string, 1:string}> $samplePrepared
	 */
	private static function checkSample(array $samplePrepared): void {
		if (!array_is_list($samplePrepared)) {
			throw new InvalidArgumentException('Invalid backtrace keys order');
		}
	}

	/**
	 * @param array<int, array{0:string, 1:string}> $samplePrepared
	 * @return string
	 */
	private static function stringifyTrace(array $samplePrepared): string {
		$row = [];
		foreach (array_reverse($samplePrepared) as [$point, $callPath]) {
			if (count($row) === 0) {
				[$eFile, $eLine] = explode(':', $callPath);
				$eFileBaseName = basename($eFile);
				$row[] = "$point ({$eFileBaseName})";
				continue;
			}

			$row[] = $point;
		}

		return implode(';', $row);
	}

	/**
	 * @return Generator<string>
	 */
	private static function getLine(): Generator {
		if (STDIN === false) {
			throw new RuntimeException('Can\'t open STDIN');
		}

		while (!feof(STDIN)) {
			$line = trim(fgets(STDIN));

			//Skip comments from phpspy
			if (str_starts_with($line, '#')) {
				continue;
			}

			//fix trace with eval
			if (str_contains($line, ' : eval()\'d code:')) {
				$line = preg_replace('~\((\d+)\) : eval\(\)\'d code:.*$~u', ':$1', $line);
			}

			yield $line;
		}

	}

	private function groupTrace(string $key): void {
		if (!array_key_exists($key, $this->results)) {
			$this->results[$key] = 0;
		}
		$this->results[$key]++;
	}

	private function sendResults(bool $force = false): void {
		if (!$force && time() < $this->tsEnd && count($this->results) < $this->batchLimit) {
			return;
		}

		if (count($this->results) > 0) {
			$this->sender->sendSample($this->tsStart, time(), $this->results);
		}

		$this->init();
	}
}