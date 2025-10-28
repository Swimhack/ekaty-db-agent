<?php

namespace EkatyAgent\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use EkatyAgent\Bootstrap;

class SyncCommand extends Command
{
    protected static $defaultName = 'sync';
    protected static $defaultDescription = 'Sync restaurants from Google Places API';

    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command synchronizes restaurant data from Google Places API to the eKaty database')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force sync even if disabled in config')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making database changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('eKaty Restaurant Sync');

        // Check if sync is enabled
        if (!($this->config['sync_enabled'] ?? true) && !$input->getOption('force')) {
            $io->warning('Sync is disabled in configuration. Use --force to override.');
            return Command::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            $io->note('Running in DRY-RUN mode - no changes will be made');
        }

        try {
            $io->section('Initializing');
            
            $container = Bootstrap::createContainer($this->config);
            $syncEngine = $container['syncEngine'];
            $logger = $container['logger'];

            $io->info('Starting sync process...');
            
            $startTime = microtime(true);
            $stats = $syncEngine->sync();
            $duration = round(microtime(true) - $startTime, 2);

            $io->section('Sync Complete');
            
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Discovered', $stats['discovered']],
                    ['Details Fetched', $stats['detailed']],
                    ['Transformed', $stats['transformed']],
                    ['Imported', $stats['imported']],
                    ['Errors', $stats['errors']],
                    ['Stale Restaurants', $stats['stale']],
                    ['Duration', $duration . 's'],
                ]
            );

            $io->table(
                ['Database Stats', 'Count'],
                [
                    ['Total Restaurants', $stats['total'] ?? 0],
                    ['Active', $stats['active'] ?? 0],
                    ['Inactive', $stats['inactive'] ?? 0],
                    ['Average Rating', $stats['avg_rating'] ?? 0],
                ]
            );

            if ($stats['errors'] > 0) {
                $io->warning("Completed with {$stats['errors']} errors. Check logs for details.");
            } else {
                $io->success('Sync completed successfully!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Sync failed: ' . $e->getMessage());
            
            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), 'TRACE', 'fg=white;bg=red', ' ', true);
            }

            return Command::FAILURE;
        }
    }
}
