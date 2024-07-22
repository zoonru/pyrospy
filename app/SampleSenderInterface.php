<?php

declare(strict_types=1);

namespace Zoon\PyroSpy;

interface SampleSenderInterface
{
    public function sendSample(Sample $sample): bool;
}