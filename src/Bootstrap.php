<?php

namespace EkatyAgent;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use EkatyAgent\Google\PlacesClient;
use EkatyAgent\Database\DatabaseManager;
use EkatyAgent\Transform\DataTransformer;
use EkatyAgent\Sync\SyncEngine;
use EkatyAgent\Error\ErrorHandler;

class Bootstrap
{
    public static function createContainer(array $config): array
    {
        // Logger
        $logger = self::createLogger($config);

        // Google Places Client
        $placesClient = new PlacesClient(
            $config['google_api_key'],
            $logger,
            (int) ($config['rate_limit_delay'] ?? 100)
        );

        // Database Manager
        $database = new DatabaseManager([
            'type' => $config['db_type'] ?? 'sqlite',
            'path' => $config['db_path'] ?? './data/ekaty.db',
            'host' => $config['db_host'] ?? null,
            'port' => $config['db_port'] ?? 5432,
            'name' => $config['db_name'] ?? null,
            'user' => $config['db_user'] ?? null,
            'pass' => $config['db_pass'] ?? null,
        ], $logger);

        // Data Transformer
        $transformer = new DataTransformer($logger);

        // Error Handler
        $errorHandler = new ErrorHandler($logger, $config);

        // Sync Engine
        $syncEngine = new SyncEngine(
            $placesClient,
            $database,
            $transformer,
            $logger,
            $config
        );

        return [
            'logger' => $logger,
            'placesClient' => $placesClient,
            'database' => $database,
            'transformer' => $transformer,
            'errorHandler' => $errorHandler,
            'syncEngine' => $syncEngine,
        ];
    }

    private static function createLogger(array $config): Logger
    {
        $logger = new Logger('ekaty-agent');

        // Console handler
        $consoleHandler = new StreamHandler('php://stdout', self::getLogLevel($config));
        $consoleHandler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'Y-m-d H:i:s'
        ));
        $logger->pushHandler($consoleHandler);

        // File handler (rotating)
        $logDir = $config['log_dir'] ?? './logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $fileHandler = new RotatingFileHandler(
            $logDir . '/agent.log',
            (int) ($config['log_retention_days'] ?? 30),
            self::getLogLevel($config)
        );
        $logger->pushHandler($fileHandler);

        return $logger;
    }

    private static function getLogLevel(array $config): int
    {
        $level = strtoupper($config['log_level'] ?? 'INFO');
        
        return match($level) {
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            'CRITICAL' => Logger::CRITICAL,
            default => Logger::INFO,
        };
    }
}
