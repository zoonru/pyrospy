<?php

namespace Zoon\PyroSpy;

use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Sync\LocalSemaphore;
use function Amp\async;
use function Amp\delay;

final class Sender {

	private readonly HttpClient $client;
	private readonly LocalSemaphore $concurrentRequestLimit;

	/**
	 * @param array<string, string> $tags
	 */
	public function __construct(
		private readonly string $pyroscopeHost,
		private readonly string $appName,
		private readonly int $rateHz,
		private readonly array $tags,
		int $concurrentRequestLimit,
	) {
		$this->client = (new HttpClientBuilder())
			->retry(0)
			->followRedirects(0)
			->build()
		;
		$this->concurrentRequestLimit = new LocalSemaphore($concurrentRequestLimit);
	}

	/**
	 * @param array<string, int> $samples
	 * @param array<string, string> $tags
	 * @return Future<bool>
	 */
	public function sendSample(int $fromTs, int $toTs, array $samples, array $tags): Future {
		return async(function () use ($fromTs, $toTs, $samples, $tags) {
			$lock = $this->concurrentRequestLimit->acquire();
			try {
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
			} finally {
				$lock->release();
			}
		});
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