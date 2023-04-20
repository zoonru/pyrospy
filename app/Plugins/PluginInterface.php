<?php

namespace Zoon\PyroSpy\Plugins;

interface PluginInterface {
	/**
	 * @param array<string, mixed> $tags
	 * @param array<int|string, array{0:string, 1:string}> $trace
	 * @return array{0:array<string, mixed>, 1:array<int|string, array{0:string, 1:string}>}
	 */
	public function process(array $tags, array $trace): array;
}