<?php

namespace Zoon\PyroSpy;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

final class Sender {

	private readonly HttpClient $client;

	/**
	 * @param array<string, string> $tags
	 */
	public function __construct(
		private readonly string $pyroscopeHost,
		private readonly string $appName,
		private readonly int $rateHz,
		private readonly array $tags,
	) {
		$this->client = (new HttpClientBuilder())
			->retry(0)
			->followRedirects(0)
			->build()
		;
	}

	/**
	 * @param array<string, int> $samples
	 * @param array<string, string> $tags
	 */
	public function sendSample(int $fromTs, int $toTs, array $samples, array $tags): bool {
		$url = $this->getUrl($tags, $fromTs, $toTs);
		try {
			$request = new Request($url, 'POST', self::prepareBody($samples));
			$request->setTcpConnectTimeout(5 * 60);
			$request->setTlsHandshakeTimeout(5 * 60);
			$request->setTransferTimeout(60 * 60);
			$request->setInactivityTimeout(60 * 60);
			$response = $this->client->request($request);
			if ($response->getStatus() === 200) {
				return true;
			} else {
				printf("\nerror on request to url '%s', status code: %s", $url, $response->getStatus());
				return false;
			}
		} catch (\Throwable $exception) {
			printf("\nerror on request to url '%s', exception message: %s", $url, $exception->getMessage());
			return false;
		}
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