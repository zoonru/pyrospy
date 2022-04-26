<?php

namespace Zoon\PyroSpy\Commands;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zoon\PyroSpy\Processor;
use Zoon\PyroSpy\Sender;

class RunCommand extends Command {

	protected function configure(): void {
		$this
			->setName('run')
			->setDescription('Main command')
			->setHelp('Adapts data from phpspy and sends samples to pyroscope')
			->setDefinition(new InputDefinition([
				new InputOption(
					'pyroscope',
					'p',
					InputOption::VALUE_REQUIRED,
					'Url of the pyroscope server. Example: https://your-pyroscope-sever.com'
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
					100
				),
				new InputOption(
					'interval',
					'i',
					InputOption::VALUE_REQUIRED,
					'Maximum time between requests to pyroscope server',
					10
				),
				new InputOption(
					'batch',
					'b',
					InputOption::VALUE_REQUIRED,
					'Maximum number of traces in request to pyroscope server',
					250
				),
				new InputOption(
					'tags',
					't',
					InputOption::VALUE_IS_ARRAY|InputOption::VALUE_REQUIRED,
					'Added tags to samples. Example: host=server1, role=cli',
					[]
				),
			]))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$pyroscope = (string)$input->getOption('pyroscope');
		if (!$pyroscope) {
			throw new InvalidArgumentException('Option pyroscope is required');
		}
		$app = (string)$input->getOption('app');
		if (!$app) {
			throw new InvalidArgumentException('Option app is required');
		}

		$interval = (int)$input->getOption('interval');
		if ($interval <= 0) {
			throw new InvalidArgumentException('Interval must be positive value');
		}
		$batch = (int)$input->getOption('batch');
		if ($batch <= 0) {
			throw new InvalidArgumentException('Batch must be positive value');
		}
		$rateHz = (int)$input->getOption('rateHz');
		if ($rateHz <= 0) {
			throw new InvalidArgumentException('rateHz must be positive value');
		}

		$tags = [];
		foreach ((array) $input->getOption('tags') as $tag) {
			if (!str_contains($tag, '=')) {
				throw new InvalidArgumentException('Invalid tag format');
			}
			[$name, $value] = explode('=', $tag);
			$tags[$name] = $value;
		}

		$processor = new Processor(
			$interval,
			$batch,
			new Sender($pyroscope, $app, $rateHz, $tags)
		);
		$processor->process();
		return Command::SUCCESS;
	}
}