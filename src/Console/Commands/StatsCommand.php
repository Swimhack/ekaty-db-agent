<?php

namespace EkatyAgent\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use EkatyAgent\Bootstrap;

class StatsCommand extends Command
{
    protected static $defaultName = 'stats';
    protected static $defaultDescription = 'Display restaurant database statistics';

    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('eKaty Database Statistics');

        try {
            $container = Bootstrap::createContainer($this->config);
            $db = $container['database'];

            $stats = $db->getStats();

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Restaurants', $stats['total']],
                    ['Active Restaurants', $stats['active']],
                    ['Inactive Restaurants', $stats['inactive']],
                    ['Average Rating', $stats['avg_rating']],
                ]
            );

            // Get stale restaurants
            $stale = $db->getStaleRestaurants(7);
            
            if (count($stale) > 0) {
                $io->section('Restaurants Not Verified in 7+ Days');
                $io->warning(count($stale) . ' restaurants need verification');
                
                $staleData = array_slice(array_map(function($r) {
                    return [
                        $r['name'],
                        $r['last_verified'] ?? 'Never',
                        $r['active'] ? 'Yes' : 'No'
                    ];
                }, $stale), 0, 10);

                $io->table(['Name', 'Last Verified', 'Active'], $staleData);
                
                if (count($stale) > 10) {
                    $io->note('Showing first 10 of ' . count($stale) . ' stale restaurants');
                }
            } else {
                $io->success('All restaurants recently verified!');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to retrieve stats: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
