<?php

declare(strict_types=1);

namespace Zoon\PyroSpy;

final class Sample {

	/**
	 * @param array<string, int> $samples
	 * @param array<string, string> $tags
	 */
	public function __construct(
		public readonly int $fromTs,
		public readonly int $toTs,
		public readonly array $samples,
		public readonly array $tags,
	) {
	}
}