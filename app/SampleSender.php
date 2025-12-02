<?php

namespace Zoon\PyroSpy;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @psalm-import-type TagsArray from Sample
 * @psalm-import-type SamplesArray from Sample
 */
final class SampleSender implements SampleSenderInterface
{
    private readonly HttpClient $client;

    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        private readonly string $pyroscopeHost,
        private readonly string $appName,
        private readonly int $rateHz,
        private readonly array $tags,
        private readonly string $authToken = '',
        private readonly SenderUnitsEnum $units = SenderUnitsEnum::Samples,
        private readonly SenderAggregationEnum $aggregation = SenderAggregationEnum::Sum,
        private readonly float $sendTimeout = 5.,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->client = (new HttpClientBuilder())
            ->retry(0)
            ->followRedirects(0)
            ->build()
        ;
    }

    public function sendSample(Sample $sample): bool
    {
        $metricsStart = microtime(true);
        $metricsSize = 0;
        $metricStatus = 'error';
        $url = $this->getUrl($sample->tags, $sample->fromTs, $sample->toTs);
        try {
            $body = self::prepareBody($sample->samples);
            $metricsSize = strlen($body);
            $request = new Request($url, 'POST', $body);
            $request->setTcpConnectTimeout($this->sendTimeout / 2.0);
            $request->setTransferTimeout($this->sendTimeout);
            $request->setHeader('Content-Type', 'text/html');
            if (!empty($this->authToken)) {
                $request->addHeader('Authorization', 'Bearer ' . $this->authToken);
            }
            $response = $this->client->request($request);
            if ($response->getStatus() === 200) {
                $metricStatus = 'success';
                return true;
            }
            $this->logger->warning('Non-200 response from pyroscope', ['url' => $url, 'status' => $response->getStatus()]);
            return false;
        } catch (\Throwable $exception) {
            $this->logger->error('Exception sending sample to pyroscope', ['url' => $url, 'message' => $exception->getMessage()]);
            return false;
        } finally {
            $metricsDuration = round(microtime(true) - $metricsStart, 3);
            $this->logger->info("Sample sent: $metricStatus", [
                'duration' => $metricsDuration,
                'samples_count' => count($sample->samples),
                'size_bytes' => $metricsSize,
                'status' => $metricStatus,
            ]);
        }
    }

    /**
     * @param TagsArray $additionalTags
     */
    private function getAppName(array $additionalTags = []): string
    {
        $tags = [];
        foreach (array_merge($this->tags, $additionalTags) as $name => $value) {
            $tags[] = "{$name}=$value";
        }
        return sprintf('%s{%s}', $this->appName, implode(',', $tags));
    }

    /**
     * @param SamplesArray $samples
     */
    private static function prepareBody(array $samples): string
    {
        $result = '';
        foreach ($samples as $trace => $count) {
            $result .= "{$trace} {$count}" . PHP_EOL;
        }
        return $result;
    }

    /**
     * @param TagsArray $tags
     * @param int $fromTs
     * @param int $toTs
     * @return string
     */
    private function getUrl(array $tags, int $fromTs, int $toTs): string
    {
        $params = [
            'name' => $this->getAppName($tags),
            'from' => $fromTs,
            'until' => $toTs,
            'sampleRate' => $this->rateHz,
            'format' => 'folded',
            'units' => $this->units->value,
            'aggregationType' => $this->aggregation->value,
        ];
        return $this->pyroscopeHost . "/ingest?" . http_build_query($params);
    }
}
