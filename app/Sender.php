<?php

namespace Zoon\PyroSpy;

use RuntimeException;

class Sender {

	private string $pyroscopeHost;
	/** @var resource|\CurlHandle */
	private $curl;
	private string $appName;
	/**
	 * @var array<string, string>
	 */
	private array $tags;
	private int $rateHz;

	public function __construct(string $pyroscopeHost, string $appName, int $rateHz, array $tags = []) {
		$this->pyroscopeHost = $pyroscopeHost;
		$this->curl = curl_init();
		if (!$this->curl) {
			throw new RuntimeException('Cant init curl');
		}
		$this->appName = $appName;
		$this->tags = $tags;
		$this->rateHz = $rateHz;
	}

	/**
	 * @param array<string, int> $samples
	 * @param array<string,string> $tags
	 */
	public function sendSample(int $fromTs, int $toTs, array $samples, array $tags = []): bool {
		$url = $this->getUrl($tags, $fromTs, $toTs);
		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, self::prepareBody($samples));
		curl_exec($this->curl);
		$info = curl_getinfo($this->curl);

		if ($info['http_code'] !== 200) {
			printf("\nerror on request to url '%s', status code: %s, error: %s", $url, $info['http_code'], curl_error($this->curl));
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, int> $additionalTags
	 */
	private function getAppName(array $additionalTags = []): string {
		$tags = [];
		foreach (array_merge($this->tags, $additionalTags) as $name => $value) {
			$tags[] = "{$name}=$value";
		}
		return sprintf('%s{%s}', $this->appName, implode(',', $tags));
	}

	/**
	 * @param array<string, int> $samples
	 */
	private static function prepareBody(array $samples): string {
		$result = '';
		foreach ($samples as $trace => $count) {
			$result .= "{$trace} {$count}" . PHP_EOL;
		}
		return $result;
	}

	public function getUrl(array $tags, int $fromTs, int $toTs): string {
		$params = [
			'name' => $this->getAppName($tags),
			'from' => $fromTs,
			'until' => $toTs,
			'sampleRate' => $this->rateHz,
			'format' => 'folded',
		];
		return $this->pyroscopeHost . "/ingest?" . http_build_query($params);
	}
}