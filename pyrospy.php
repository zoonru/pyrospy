<?php

require 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use Zoon\PyroSpy\Commands\RunCommand;

$application = new Application();
$application->add(new RunCommand());
$application->run();

