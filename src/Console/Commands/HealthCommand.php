<?php

namespace EkatyAgent\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use EkatyAgent\Bootstrap;

class HealthCommand extends Command
{
    protected static $defaultName = 'health';
    protected static $defaultDescription = 'Check system health and connectivity';

    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Health Check');

        $checks = [
            'PHP Version' => $this->checkPhpVersion(),
            'Database Connection' => $this->checkDatabase(),
            'Google API Key' => $this->checkApiKey(),
            'Google API Access' => $this->checkGoogleApi(),
            'Disk Space' => $this->checkDiskSpace(),
        ];

        $allPassed = true;
        $results = [];

        foreach ($checks as $check => $result) {
            $status = $result['success'] ? '✓' : '✗';
            $message = $result['message'] ?? '';
            
            $results[] = [$check, $status, $message];
            
            if (!$result['success']) {
                $allPassed = false;
            }
        }

        $io->table(['Check', 'Status', 'Details'], $results);

        if ($allPassed) {
            $io->success('All health checks passed!');
            return Command::SUCCESS;
        } else {
            $io->error('Some health checks failed');
            return Command::FAILURE;
        }
    }

    private function checkPhpVersion(): array
    {
        $version = PHP_VERSION;
        $required = '8.1.0';
        
        $success = version_compare($version, $required, '>=');
        
        return [
            'success' => $success,
            'message' => "PHP $version" . ($success ? '' : " (requires $required+)")
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $container = Bootstrap::createContainer($this->config);
            $db = $container['database'];
            $stats = $db->getStats();
            
            return [
                'success' => true,
                'message' => "{$stats['total']} restaurants"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkApiKey(): array
    {
        $apiKey = $this->config['google_api_key'] ?? '';
        
        if (empty($apiKey) || $apiKey === 'your_api_key_here') {
            return [
                'success' => false,
                'message' => 'API key not configured'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Configured (' . substr($apiKey, 0, 10) . '...)'
        ];
    }

    private function checkGoogleApi(): array
    {
        try {
            $container = Bootstrap::createContainer($this->config);
            $placesClient = $container['placesClient'];
            
            // Try a simple search
            $result = $placesClient->nearbySearch(
                (float) $this->config['location_lat'],
                (float) $this->config['location_lng'],
                1000,
                'restaurant'
            );
            
            $count = count($result['results'] ?? []);
            
            return [
                'success' => true,
                'message' => "Connected (found $count nearby)"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $free = disk_free_space('.');
        $total = disk_total_space('.');
        
        $freeGB = round($free / 1024 / 1024 / 1024, 2);
        $percentFree = round(($free / $total) * 100, 1);
        
        $success = $percentFree > 10;
        
        return [
            'success' => $success,
            'message' => "{$freeGB}GB free ({$percentFree}%)"
        ];
    }
}
