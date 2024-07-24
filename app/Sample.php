<?php

declare(strict_types=1);

namespace Zoon\PyroSpy;

/**
 * @phpstan-type TagsArray array<string, string>
 * @phpstan-type TraceStruct array<int, array<int, string>>
 * @phpstan-type SamplesArray array<string, int>
 */
final class Sample
{
    /**
     * @param SamplesArray $samples
     * @param TagsArray $tags
     */
    public function __construct(
        public readonly int $fromTs,
        public readonly int $toTs,
        public readonly array $samples,
        public readonly array $tags,
    ) {}
}
