<?php

namespace Zoon\PyroSpy\Plugins;

use Zoon\PyroSpy\Sample;

/**
 * @psalm-import-type TagsArray from Sample
 * @psalm-import-type TraceStruct from Sample
 */
final class SkipSleepTraces implements PluginInterface
{
    private const SYSTEM_FRAMES = [
        'Fiber::start',
        'Fiber::resume',
        'pcntl_wait',
        'pcntl_waitpid',
        'socket_read',
        'stream_select',
        'socket_select',
    ];

    /**
     * @param TagsArray $tags
     * @param TraceStruct $trace
     * @return array{0: TagsArray, 1: TraceStruct}
     */
    public function process(array $tags, array $trace): array
    {
        foreach ($trace as $frame) {
            if (\in_array($frame[0], self::SYSTEM_FRAMES, true)) {
                return [$tags, []];
            }
        }

        return [$tags, $trace];
    }
}
