<?php

namespace Zoon\PyroSpy;

/**
 * @psalm-import-type TagsArray from Sample
 * @psalm-type ResultsArray=array<string, array<string, int>>
 */
abstract class TraceAggregatorAbstract
{
    /**
     * @var ResultsArray
     */
    protected array $results;

    public function clear(): void
    {
        $this->results = [];
    }

    /**
     * @param TagsArray $tags
     * @param string $key
     */
    abstract public function addTrace(array $tags, string $key): void;

    public function countGrouppedTraces(): int {
        $count = 0;
        foreach ($this->results as $tagResuts) {
            $count += count($tagResuts);
        }
        return $count;
    }

    /**
     * @return ResultsArray
     */
    public function getGrouppedTraces(): array {
        return $this->results;
    }
}