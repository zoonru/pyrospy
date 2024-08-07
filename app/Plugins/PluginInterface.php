<?php

namespace Zoon\PyroSpy\Plugins;

use Zoon\PyroSpy\Sample;

/**
 * @psalm-import-type TagsArray from Sample
 * @psalm-import-type TraceStruct from Sample
 */

interface PluginInterface
{
    /**
     * @param TagsArray $tags
     * @param TraceStruct $trace
     * @return array{0:TagsArray, 1:TraceStruct}
     */
    public function process(array $tags, array $trace): array;
}
