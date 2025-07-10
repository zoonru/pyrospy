<?php

namespace Zoon\PyroSpy;

/**
 * @psalm-import-type ResultsArray from TraceAggregatorAbstract
 */
class MemoryTraceAggregator extends TraceAggregatorAbstract
{
    /**
     * @var array<string, array<string, list<int>>>
     */
    protected array $results;
    /**
     * @inheritDoc
     */
    public function addTrace(array $tags, string $key): void
    {
        ksort($tags);
        if (!array_key_exists('mem', $tags)) {
            return;
        }
        $memSize = $tags['mem'];
        unset($tags['mem']);

        $tagsKey = serialize($tags);
        if (!array_key_exists($tagsKey, $this->results)) {
            $this->results[$tagsKey] = [];
        }
        if (!array_key_exists($key, $this->results[$tagsKey])) {
            $this->results[$tagsKey][$key] = [];
        }
        $this->results[$tagsKey][$key][] = $memSize;
    }

    /**
     * @return ResultsArray
     */
    public function getGrouppedTraces(): array
    {
        $results = [];

        foreach ($this->results as $tagsKey => $tagResuts) {
            foreach ($tagResuts as $key => $traces) {
                if ($traces) {
                    $results[$tagsKey][$key] = (int)(array_sum($traces)/count($traces));
                } else {
                    $results[$tagsKey][$key] = 0;
                }
            }
        }

        return $results;
    }
}