<?php

namespace Zoon\PyroSpy;

class CpuTraceAggregator extends TraceAggregatorAbstract
{
    /**
     * @inheritDoc
     */
    public function addTrace(array $tags, string $key): void
    {
        ksort($tags);
        unset($tags['mem']);
        $tagsKey = serialize($tags);
        if (!array_key_exists($tagsKey, $this->results)) {
            $this->results[$tagsKey] = [];
        }
        if (!array_key_exists($key, $this->results[$tagsKey])) {
            $this->results[$tagsKey][$key] = 0;
        }
        $this->results[$tagsKey][$key]++;
    }
}