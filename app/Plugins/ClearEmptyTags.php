<?php

namespace Zoon\PyroSpy\Plugins;

class ClearEmptyTags implements PluginInterface {

	public function process(array $tags, array $trace): array {
		unset($tags['ts']);

		foreach ($tags as $name => $value) {
			$value = trim($value);
			if (!$value || $value === '-') {
				unset($tags[$name]);
			}
		}
		return [$tags, $trace];
	}
}