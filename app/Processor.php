<?php

namespace Zoon\PyroSpy;

use Amp\ByteStream\ReadableStream;
use Amp\Future;
use Amp\Pipeline\Queue;
use Generator;
use InvalidArgumentException;
use Throwable;
use Zoon\PyroSpy\Plugins\PluginInterface;

use function Amp\async;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\splitLines;

/**
 * @psalm-import-type TagsArray from Sample
 * @psalm-import-type SamplesArray from Sample
 * @psalm-import-type TraceStruct from Sample
 */

final class Processor
{
    private int $tsStart = 0;
    private int $tsEnd;
    /**
     * @var array<string, array<string, int>>
     */
    private array $results;
    /** @var Queue<Sample> */
    private readonly Queue $queue;

    /**
     * @param list<PluginInterface> $plugins
     */
    public function __construct(
        private readonly int $interval,
        private readonly int $batchLimit,
        private readonly TraceAggregatorAbstract $aggregator,
        private readonly SampleSenderInterface $sender,
        private readonly array $plugins,
        int $sendSampleFutureLimit,
        private readonly int $concurrentRequestLimit,
        private ?\Amp\ByteStream\ReadableStream $dataReader = null,
    ) {
        if ($this->dataReader === null) {
            $this->dataReader = getStdin();
        }
        $this->init();
        $this->queue = new Queue($sendSampleFutureLimit);
    }

    private function init(): void
    {
        $this->aggregator->clear();
        $this->tsStart = time();
        $this->tsEnd = $this->tsStart + $this->interval;
    }

    public function process(): void
    {
        Future\await([
            $this->runProducer(),
            $this->runConsumer(),
        ]);
    }

    /**
     * @psalm-return Future<void>
     */
    private function runProducer(): Future
    {
        return async(function (): void {
            $trace = [];

            foreach ($this->getLine() as $line) {
                $isEndOfTrace = $line === '';

                if (!$isEndOfTrace) {
                    $trace[] = $line;
                }

                if ($isEndOfTrace && count($trace) > 0) {
                    try {
                        try {
                            $tags = self::extractTags($trace);
                            $tracePrepared = self::prepareTrace($trace);
                            self::checkTrace($tracePrepared);
                        } catch (Throwable $e) {
                            echo $e->getMessage() . PHP_EOL;
                            /**
                             * @psalm-suppress ForbiddenCode
                             */
                            var_dump($trace);
                            continue;
                        }

                        foreach ($this->plugins as $plugin) {
                            [$tags, $tracePrepared] = $plugin->process($tags, $tracePrepared);
                            if ($tracePrepared === []) {
                                continue 2;
                            }
                        }

                        $key = self::stringifyTrace($tracePrepared);
                        $this->aggregator->addTrace($tags, $key);

                        $currentTime = time();

                        if ($currentTime < $this->tsEnd && $this->aggregator->countGrouppedTraces() < $this->batchLimit) {
                            continue;
                        }

                        foreach ($this->aggregator->getGrouppedTraces() as $tagSerialized => $results) {
                            $this->queue->push(new Sample($this->tsStart, $currentTime, $results, unserialize($tagSerialized)));
                        }

                        $this->init();
                    } finally {
                        $trace = [];
                    }
                }
            }

            $currentTime = time();
            foreach ($this->aggregator->getGrouppedTraces() as $tagSerialized => $results) {
                $this->queue->push(new Sample($this->tsStart, $currentTime, $results, unserialize($tagSerialized)));
            }

            $this->queue->complete();
        });
    }

    /**
     * @psalm-return Future<void>
     */
    private function runConsumer(): Future
    {
        return async(function (): void {
            $this
                ->queue
                ->pipe()
                ->unordered()
                ->concurrent($this->concurrentRequestLimit)
                ->forEach(function (Sample $sample): void {
                    $this->sender->sendSample($sample);
                });
        });
    }

    /**
     * @param array<string> $sample
     * @return TraceStruct
     */
    private static function prepareTrace(array $sample): array
    {
        $samplePrepared = [];
        foreach ($sample as $item) {
            if (!is_numeric(substr($item, 0, 1))) {
                continue;
            }
            $item = explode(' ', $item);
            if (count($item) !== 3) {
                throw new InvalidArgumentException('Invalid sample shape');
            }
            $key = (int) array_shift($item);
            $samplePrepared[$key] = $item;
        }
        return $samplePrepared;
    }

    /**
     * @param list<string> $sample
     * @return TagsArray
     */
    private static function extractTags(array $sample): array
    {
        $tags = [];
        foreach ($sample as $item) {
            if (substr($item, 0, 1) !== '#') {
                continue;
            }
            $item = explode(' ', $item);
            if (count($item) !== 4) {
                continue;
            }

            //# uri = /stat.php
            [$hashtag, $tag, $equalsign, $value] = $item;

            if ($tag === 'ts') {
                continue;
            }
            if ($tag === 'mem') {
                // # mem 1653800 1659424
                [$hashtag, $tag, $value, $maxUsage] = $item;
                $value = (int) $value;
            }

            $tags[$tag] = $value;
        }
        return $tags;
    }

    /**
     * @param TraceStruct $tracePrepared
     */
    private static function checkTrace(array $tracePrepared): void
    {
        if (!array_is_list($tracePrepared)) {
            throw new InvalidArgumentException('Invalid backtrace keys order');
        }
    }

    /**
     * @param TraceStruct $tracePrepared
     * @return string
     */
    private static function stringifyTrace(array $tracePrepared): string
    {
        $row = [];
        foreach (array_reverse($tracePrepared) as [$point, $callPath]) {
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
    private function getLine(): Generator
    {
        assert($this->dataReader instanceof ReadableStream);
        foreach (splitLines($this->dataReader) as $line) {
            $line = trim($line);
            $line = self::fixEvalLine($line);
            $line = self::fixClosureLine($line);

            yield $line;
        }
    }

    private static function fixEvalLine(string $line): string
    {

        if (strpos($line, ' : eval()\'d code:') !== false) {
            $line = preg_replace('~\((\d+)\) : eval\(\)\'d code:.*$~u', ':$1', $line);
            if ($line === null) {
                $line = 'eval() code replacement failure';
            }
        }
        return $line;
    }

    private static function fixClosureLine(string $line): string
    {
        if (preg_match('~({closure.*}):\d+~u', $line, $closure) && str_contains($closure[1], ' ')) {
            $line = str_replace($closure[1], sprintf("{closure:%s} <closure>", md5($closure[1])), $line);
        }
        return $line;
    }
}
