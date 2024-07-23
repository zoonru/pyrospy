<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

class SampleTest extends PHPUnit\Framework\TestCase
{
    #[DataProvider('batchAggregationProvider')]
    public function testBatchAggregation(int $batchLimit, int $samplesSent, string $traces): void
    {
        $sender = $this->createMock(\Zoon\PyroSpy\SampleSenderInterface::class);
        $sender->expects($this->exactly($samplesSent))->method('sendSample');

        $processor = new \Zoon\PyroSpy\Processor(
            interval: 100500,
            batchLimit: $batchLimit,
            sender: $sender,
            plugins: [],
            sendSampleFutureLimit: 999999999,
            concurrentRequestLimit: 1,
            dataReader: new \Amp\ByteStream\Payload($traces),
        );

        $processor->process();
    }

    public static function batchAggregationProvider(): Generator
    {
        yield
        'It should send 3 samples (no grouping due batch limit of 1)' =>
        [
            'batchLimit' => 1,
            'samplesSent' => 3,
            'traces' => <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1

0 usleep <internal>:-1
1 <main> <internal>:-1

0 usleep <internal>:-1
1 <main> <internal>:-1


EOT,
        ];

        yield 'It should send 1 samples (all 3 traces aggregated into 1 sample)' => [
            'batchLimit' => 2,
            'samplesSent' => 1,
            'traces' => <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1

0 usleep <internal>:-1
1 <main> <internal>:-1

0 usleep <internal>:-1
1 <main> <internal>:-1


EOT,
        ];
    }

    #[DataProvider('tagsAggregationProvider')]
    public function testTagsAggregation(int $batchLimit, int $samplesSent, string $traces): void
    {
        $sender = $this->createMock(\Zoon\PyroSpy\SampleSenderInterface::class);
        $sender->expects($this->exactly($samplesSent))->method('sendSample');

        $processor = new \Zoon\PyroSpy\Processor(
            interval: 100500,
            batchLimit: $batchLimit,
            sender: $sender,
            plugins: [],
            sendSampleFutureLimit: 999999999,
            concurrentRequestLimit: 1,
            dataReader: new \Amp\ByteStream\Payload($traces),
        );

        $processor->process();
    }

    public static function tagsAggregationProvider(): Generator
    {
        yield 'It should send 3 samples (no aggregation because of limit and traces order)' => [
            'batchLimit' => 2,
            'samplesSent' => 3,
            'traces' => <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostTwo

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne


EOT
            ,
        ];

        yield 'It should send 2 samples (First two traces aggregated by the same tag, and the last one goes to the second sample)' => [
            2,
            2,
            <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostTwo


EOT
            ,
        ];


        yield 'It should send 1 sample (all traces are aggregated by the same tag)' => [
            2,
            1,
            <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne

0 Spiral\Core\Container::runScope /app/vendor/spiral/framework/src/Core/src/Container.php:178
1 Spiral\Boot\AbstractKernel::serve /app/vendor/spiral/framework/src/Boot/src/AbstractKernel.php:289
2 <main> /app/app.php:1
#glopeek server.HOSTNAME = hostOne


EOT
            ,
        ];

        yield 'It should send 1 sample (invalid `different` tags are ignored, traces are aggregated)' => [
            2,
            1,
            <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne
#ts = 1721678526.060294

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne
#ts = 3821678527.060294


EOT
            ,
        ];

        yield 'It should send 2 samples (no aggregation because of different tag sets)' => [
            2,
            2,
            <<<EOT
0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne
#glopeek server.REQUEST_TIME = 1721678526.060294

0 usleep <internal>:-1
1 <main> <internal>:-1
#glopeek server.HOSTNAME = hostOne
#glopeek server.REQUEST_TIME = 3821678527.060294


EOT
            ,
        ];
    }

}
