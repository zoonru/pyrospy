<?php

namespace Zoon\PyroSpy;

use Generator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Zoon\PyroSpy\Plugins\PluginInterface;

final class Processor {

	private int $interval;
	private int $batchLimit;

	private int $tsStart = 0;
	private int $tsEnd;
	/**
	 * @var array<string, array<string, int>>
	 */
	private array $results;

	private Sender $sender;
    /** @var list<PluginInterface> */
    private array $plugins = [];


    public function __construct(int $interval, int $batchLimit, Sender $sender, array $plugins = []) {
		$this->interval = $interval;
		$this->sender = $sender;
		$this->init();
		$this->batchLimit = $batchLimit;
        $this->plugins = $plugins;
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
					$tags = self::extractTags($sample);
					$samplePrepared = self::prepareSample($sample);
					self::checkSample($samplePrepared);
				} catch (Throwable $e) {
					echo $e->getMessage() . PHP_EOL;
					var_dump($sample);
					$sample = [];
					continue;
				}

				foreach ($this->plugins as $plugin) {
					[$tags, $samplePrepared] = $plugin->process($tags, $samplePrepared);
				}
				$key = self::stringifyTrace($samplePrepared);
				$this->groupTrace($tags, $key);
				$this->sendResults();

				$sample = [];
			}
		}

		$this->sendResults(true);
	}

	/**
	 * @param list<string> $sample
	 * @return array<int|string, array{0:string, 1:string}>
	 */
	private static function prepareSample(array $sample): array {
		$samplePrepared = [];
		foreach ($sample as $item) {
			if (!is_numeric(substr($item, 0, 1))) {
				continue;
			}
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
	 * @param list<string> $sample
	 * @return
	 */
	private static function extractTags(array $sample): array {
		$tags = [];
		foreach ($sample as $item) {
			if (!is_string($item) || substr($item, 0, 1) !== '#') {
				continue;
			}
			$item = explode(' ', $item);
			if (count($item) !== 4) {
				continue;
			}
			[$hashtag, $tag, $equalsing, $value] = $item;
			$tags[$tag] = $value;
		}
		return $tags;
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

			//fix trace with eval
			if (strpos($line, ' : eval()\'d code:') !== false) {
				$line = preg_replace('~\((\d+)\) : eval\(\)\'d code:.*$~u', ':$1', $line);
			}

			yield $line;
		}

	}

	private function groupTrace(array $tags, string $key): void {
		ksort($tags);
		$tagsKey = serialize($tags);
		if (!array_key_exists($tagsKey, $this->results)) {
			$this->results[$tagsKey] = [];
		}
		if (!array_key_exists($key, $this->results[$tagsKey])) {
			$this->results[$tagsKey][$key] = 0;
		}
		$this->results[$tagsKey][$key]++;
	}

	private function sendResults(bool $force = false): void {
		if (!$force && time() < $this->tsEnd && $this->countResults() < $this->batchLimit) {
			return;
		}

		foreach ($this->results  as $tagSerialized => $results) {
			$this->sender->sendSample($this->tsStart, time(), $results, unserialize($tagSerialized));
		}

		$this->init();
	}

	private function countResults(): int {
		$count = 0;
		foreach ($this->results as $tagResuts) {
			$count += count($tagResuts);
		}
		return $count;
	}
}