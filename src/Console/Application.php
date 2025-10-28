<?php

namespace EkatyAgent\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use EkatyAgent\Console\Commands\SyncCommand;
use EkatyAgent\Console\Commands\VerifyCommand;
use EkatyAgent\Console\Commands\StatsCommand;
use EkatyAgent\Console\Commands\HealthCommand;

class Application extends BaseApplication
{
    private array $config;

    public function __construct(array $config)
    {
        parent::__construct('eKaty Restaurant Agent', '1.0.0');
        
        $this->config = $config;
        $this->addCommands([
            new SyncCommand($config),
            new VerifyCommand($config),
            new StatsCommand($config),
            new HealthCommand($config),
        ]);
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // Set timezone
        date_default_timezone_set($this->config['timezone'] ?? 'America/Chicago');

        return parent::doRun($input, $output);
    }
}
