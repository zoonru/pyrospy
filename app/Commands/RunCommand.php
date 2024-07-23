<?php

namespace Zoon\PyroSpy\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zoon\PyroSpy\Plugins\PluginInterface;
use Zoon\PyroSpy\Processor;
use Zoon\PyroSpy\SampleSender;

class RunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Main command')
            ->setHelp('Adapts data from phpspy and sends samples to pyroscope')
            ->setDefinition(new InputDefinition([
                new InputOption(
                    'pyroscope',
                    's',
                    InputOption::VALUE_REQUIRED,
                    'Url of the pyroscope server. Example: https://your-pyroscope-sever.com',
                ),
                new InputOption(
                    'pyroscopeAuthToken',
                    'auth',
                    InputOption::VALUE_OPTIONAL,
                    'Pyroscope Auth Token. Example: psx-BWlqy_dW1Wxg6oBjuCWD28HxGCkB1Jfzt-jjtqHzrkzI',
                ),
                new InputOption(
                    'app',
                    'a',
                    InputOption::VALUE_REQUIRED,
                    'Name of app. Example: app',
                ),
                new InputOption(
                    'rateHz',
                    'r',
                    InputOption::VALUE_OPTIONAL,
                    'Sample rate in Hz. Used to convert number of samples to CPU time',
                    100,
                ),
                new InputOption(
                    'concurrentRequestLimit',
                    'c',
                    InputOption::VALUE_OPTIONAL,
                    'Limiting the HTTP client to N concurrent requests, so the HTTP pyroscope server doesn\'t get overwhelmed',
                    10,
                ),
                new InputOption(
                    'sendSampleFutureLimit',
                    'f',
                    InputOption::VALUE_OPTIONAL,
                    'Limiting the Send Sample futures buffer to N so as not to get a memory overflow',
                    10_000,
                ),
                new InputOption(
                    'interval',
                    'i',
                    InputOption::VALUE_REQUIRED,
                    'Maximum time between requests to pyroscope server',
                    10,
                ),
                new InputOption(
                    'batch',
                    'b',
                    InputOption::VALUE_REQUIRED,
                    'Maximum number of traces in request to pyroscope server',
                    250,
                ),
                new InputOption(
                    'tags',
                    't',
                    InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                    'Add tags to samples. Example: host=server1, role=cli',
                    [],
                ),
                new InputOption(
                    'plugins',
                    'p',
                    InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                    'Process trace and phpspy comments/tags with custom class. Can be class or folder with classes',
                    [],
                ),
            ]))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pyroscope = (string) $input->getOption('pyroscope');
        if (!$pyroscope) {
            throw new InvalidArgumentException('Option pyroscope is required');
        }
        $app = (string) $input->getOption('app');
        if (!$app) {
            throw new InvalidArgumentException('Option app is required');
        }

        $interval = (int) $input->getOption('interval');
        if ($interval <= 0) {
            throw new InvalidArgumentException('Interval must be positive value');
        }
        $batch = (int) $input->getOption('batch');
        if ($batch <= 0) {
            throw new InvalidArgumentException('Batch must be positive value');
        }
        $rateHz = (int) $input->getOption('rateHz');
        if ($rateHz <= 0) {
            throw new InvalidArgumentException('rateHz must be positive value');
        }
        $concurrentRequestLimit = (int) $input->getOption('concurrentRequestLimit');
        if ($concurrentRequestLimit <= 0) {
            throw new InvalidArgumentException('concurrentRequestLimit must be positive value');
        }
        $sendSampleFutureLimit = (int) $input->getOption('sendSampleFutureLimit');
        if ($sendSampleFutureLimit <= 0) {
            throw new InvalidArgumentException('sendSampleFutureLimit must be positive value');
        }

        $pyroscopeAuthToken = (string) $input->getOption('pyroscopeAuthToken');

        $tags = [];
        foreach ((array) $input->getOption('tags') as $tag) {
            if (strpos($tag, '=') === false) {
                throw new InvalidArgumentException('Invalid tag format');
            }
            [$name, $value] = explode('=', $tag);
            $tags[$name] = $value;
        }

        $plugins = [];
        foreach ((array) $input->getOption('plugins') as $pluginPath) {
            if (is_dir($pluginPath)) {
                $globPath = str_replace('//', '/', $pluginPath . '/*.php');
                foreach (($files = glob($globPath)) ? $files : [] as $file) {
                    $plugins[] = self::getClassFromPath($file);
                }
            } else {
                $plugins[] = self::getClassFromPath($pluginPath);
            }
        }

        $processor = new Processor(
            $interval,
            $batch,
            new SampleSender($pyroscope, $app, $rateHz, $tags, $pyroscopeAuthToken),
            array_values(array_filter($plugins)),
            $sendSampleFutureLimit,
            $concurrentRequestLimit,
        );
        $processor->process();
        return Command::SUCCESS;
    }

    private static function getClassFromPath(string $path): ?PluginInterface
    {
        if (substr($path, -4, 4) !== '.php') {
            throw new InvalidArgumentException('Plugin must be php file');
        }
        require_once $path;
        $pathArray = explode('/', $path);
        $class = str_replace('.php', '', array_pop($pathArray));
        if (!$class) {
            return null;
        }
        $class = "Zoon\PyroSpy\Plugins\\$class";
        //@phpstan-ignore-next-line
        return new $class();
    }
}
