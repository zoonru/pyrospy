<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use Zoon\PyroSpy\Plugins\SkipSleepTraces;

class SkipSleepTracesTest extends PHPUnit\Framework\TestCase
{
    #[DataProvider('dataProvider')]
    public function testPlugin(int $batchLimit, int $samplesSent, string $traces): void
    {
        $sender = $this->createMock(\Zoon\PyroSpy\SampleSenderInterface::class);
        $sender->expects($this->exactly($samplesSent))->method('sendSample');

        $processor = new \Zoon\PyroSpy\Processor(
            interval: 100500,
            batchLimit: $batchLimit,
            sender: $sender,
            plugins: [new SkipSleepTraces()],
            sendSampleFutureLimit: 999999999,
            concurrentRequestLimit: 1,
            dataReader: new \Amp\ByteStream\Payload($traces),
        );

        $processor->process();
    }

    public static function dataProvider(): Generator
    {
        yield
        'It should send 2 samples (one is skipped due to having a system trace)' =>
        [
            'batchLimit' => 1,
            'samplesSent' => 2,
            'traces' => <<<EOT
                0 usleep <internal>:-1
                1 <main> <internal>:-1
                
                0 pcntl_wait <internal>:-1
                1 <main> <internal>:-1
                
                0 strstr <internal>:-1
                1 <main> <internal>:-1


                EOT,
        ];
    }
}
