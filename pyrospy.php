<?php

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadFile)) {
	require $autoloadFile;
} else {
	require __DIR__ . '/../../autoload.php';
}

use Symfony\Component\Console\Application;
use Zoon\PyroSpy\Commands\RunCommand;

$application = new Application();
$application->add(new RunCommand());
$application->run();

