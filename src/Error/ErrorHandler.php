<?php

namespace EkatyAgent\Error;

use Psr\Log\LoggerInterface;

class ErrorHandler
{
    private LoggerInterface $logger;
    private array $config;
    private array $errorCounts = [];

    public function __construct(LoggerInterface $logger, array $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Execute a function with retry logic
     */
    public function retry(callable $callback, string $operation, int $maxRetries = 3, int $delaySeconds = 5)
    {
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                
                $this->trackError($operation);

                if ($attempt > $maxRetries) {
                    $this->logger->error("Operation failed after $maxRetries retries", [
                        'operation' => $operation,
                        'error' => $e->getMessage(),
                        'attempts' => $attempt
                    ]);
                    throw $e;
                }

                $this->logger->warning("Retry attempt $attempt/$maxRetries", [
                    'operation' => $operation,
                    'error' => $e->getMessage()
                ]);

                sleep($delaySeconds);
            }
        }
    }

    /**
     * Track error occurrence
     */
    private function trackError(string $operation): void
    {
        if (!isset($this->errorCounts[$operation])) {
            $this->errorCounts[$operation] = 0;
        }
        $this->errorCounts[$operation]++;
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(): array
    {
        return $this->errorCounts;
    }

    /**
     * Check if error threshold exceeded
     */
    public function isErrorThresholdExceeded(string $operation, int $threshold = 10): bool
    {
        return ($this->errorCounts[$operation] ?? 0) > $threshold;
    }

    /**
     * Reset error counts
     */
    public function resetErrorCounts(): void
    {
        $this->errorCounts = [];
    }

    /**
     * Handle fatal error
     */
    public function handleFatal(\Throwable $e, string $context = 'unknown'): void
    {
        $this->logger->critical('Fatal error occurred', [
            'context' => $context,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Send alert if configured
        if ($this->config['alert_enabled'] ?? false) {
            $this->sendAlert($e, $context);
        }
    }

    /**
     * Send error alert
     */
    private function sendAlert(\Throwable $e, string $context): void
    {
        try {
            $message = sprintf(
                "eKaty Agent Fatal Error\n\nContext: %s\nError: %s\nFile: %s:%d\nTime: %s",
                $context,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                date('Y-m-d H:i:s')
            );

            // Email alert
            if ($email = $this->config['alert_email'] ?? null) {
                $this->sendEmailAlert($email, $message);
            }

            // Webhook alert
            if ($webhookUrl = $this->config['alert_webhook_url'] ?? null) {
                $this->sendWebhookAlert($webhookUrl, $e, $context);
            }

        } catch (\Exception $alertError) {
            $this->logger->error('Failed to send alert', [
                'error' => $alertError->getMessage()
            ]);
        }
    }

    private function sendEmailAlert(string $email, string $message): void
    {
        $subject = 'eKaty Agent Error Alert';
        $headers = [
            'From' => 'noreply@ekaty.com',
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];

        mail($email, $subject, $message, $headers);
        
        $this->logger->info('Email alert sent', ['to' => $email]);
    }

    private function sendWebhookAlert(string $url, \Throwable $e, string $context): void
    {
        $payload = json_encode([
            'level' => 'critical',
            'context' => $context,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => date('c')
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        $this->logger->info('Webhook alert sent', ['url' => $url]);
    }
}
