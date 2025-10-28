<?php

namespace EkatyAgent\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use EkatyAgent\Bootstrap;

class VerifyCommand extends Command
{
    protected static $defaultName = 'verify';
    protected static $defaultDescription = 'Verify and update a specific restaurant by Google Place ID';

    private array $config;

    public function __construct(array $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Verify a specific restaurant using its Google Place ID')
            ->addArgument('place-id', InputArgument::REQUIRED, 'Google Place ID of the restaurant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $placeId = $input->getArgument('place-id');
        
        $io->title('Verify Restaurant');
        $io->info("Verifying restaurant: $placeId");

        try {
            $container = Bootstrap::createContainer($this->config);
            $syncEngine = $container['syncEngine'];

            $result = $syncEngine->verifyRestaurant($placeId);

            if ($result['success']) {
                $io->success("Restaurant verified: {$result['name']}");
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $result['id']],
                        ['Name', $result['name']],
                        ['Place ID', $placeId],
                    ]
                );
            } else {
                $io->error("Verification failed: {$result['error']}");
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Verification failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
