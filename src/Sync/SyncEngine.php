<?php

namespace EkatyAgent\Sync;

use EkatyAgent\Google\PlacesClient;
use EkatyAgent\Database\DatabaseManager;
use EkatyAgent\Transform\DataTransformer;
use Psr\Log\LoggerInterface;

class SyncEngine
{
    private PlacesClient $placesClient;
    private DatabaseManager $db;
    private DataTransformer $transformer;
    private LoggerInterface $logger;
    private array $config;
    private array $stats;

    public function __construct(
        PlacesClient $placesClient,
        DatabaseManager $db,
        DataTransformer $transformer,
        LoggerInterface $logger,
        array $config
    ) {
        $this->placesClient = $placesClient;
        $this->db = $db;
        $this->transformer = $transformer;
        $this->logger = $logger;
        $this->config = $config;
        $this->resetStats();
    }

    /**
     * Execute full sync process
     */
    public function sync(): array
    {
        $this->logger->info('=== Starting Restaurant Sync ===');
        $startTime = microtime(true);
        $this->resetStats();

        try {
            // Step 1: Discover restaurants
            $this->logger->info('Step 1: Discovering restaurants from Google Places');
            $places = $this->discoverRestaurants();
            $this->stats['discovered'] = count($places);

            // Step 2: Fetch detailed information
            $this->logger->info('Step 2: Fetching detailed information', [
                'count' => count($places)
            ]);
            $detailedPlaces = $this->fetchDetails($places);
            $this->stats['detailed'] = count($detailedPlaces);

            // Step 3: Transform data
            $this->logger->info('Step 3: Transforming data to eKaty schema');
            $restaurants = $this->transformer->transformPlaces($detailedPlaces);
            $this->stats['transformed'] = count($restaurants);

            // Step 4: Import to database
            $this->logger->info('Step 4: Importing to database');
            $this->importRestaurants($restaurants);

            // Step 5: Cleanup
            $this->logger->info('Step 5: Running cleanup tasks');
            $this->cleanup();

            // Calculate duration
            $duration = round(microtime(true) - $startTime, 2);
            $this->stats['duration'] = $duration;
            $this->stats['success'] = true;

            // Log audit
            $this->db->logAudit('Restaurant', 'system', 'RESTAURANT_SYNC', $this->stats);

            $this->logger->info('=== Sync Complete ===', $this->stats);

            return $this->stats;

        } catch (\Exception $e) {
            $this->stats['success'] = false;
            $this->stats['error'] = $e->getMessage();
            $this->stats['duration'] = round(microtime(true) - $startTime, 2);

            $this->logger->error('Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->db->logAudit('Restaurant', 'system', 'RESTAURANT_SYNC_FAILED', null, [
                'error' => $e->getMessage(),
                'stats' => $this->stats
            ]);

            throw $e;
        }
    }

    /**
     * Discover restaurants using comprehensive search
     */
    private function discoverRestaurants(): array
    {
        $lat = (float) $this->config['location_lat'];
        $lng = (float) $this->config['location_lng'];
        $radius = (int) $this->config['search_radius'];

        return $this->placesClient->searchAllRestaurants($lat, $lng, $radius);
    }

    /**
     * Fetch detailed information for each restaurant
     */
    private function fetchDetails(array $places): array
    {
        $detailed = [];
        $maxRetries = (int) ($this->config['max_retries'] ?? 3);
        $retryDelay = (int) ($this->config['retry_delay'] ?? 5);

        foreach ($places as $index => $place) {
            $placeId = $place['place_id'] ?? null;
            
            if (!$placeId) {
                $this->logger->warning('Skipping place without place_id');
                continue;
            }

            $this->logger->debug("Fetching details", [
                'progress' => ($index + 1) . '/' . count($places),
                'place_id' => $placeId,
                'name' => $place['name'] ?? 'Unknown'
            ]);

            $retries = 0;
            while ($retries <= $maxRetries) {
                try {
                    $details = $this->placesClient->getPlaceDetails($placeId);
                    $detailed[] = $details;
                    break;
                } catch (\Exception $e) {
                    $retries++;
                    
                    if ($retries > $maxRetries) {
                        $this->logger->error('Failed to fetch place details after retries', [
                            'place_id' => $placeId,
                            'error' => $e->getMessage(),
                            'retries' => $maxRetries
                        ]);
                        $this->stats['errors']++;
                    } else {
                        $this->logger->warning('Retrying place details', [
                            'place_id' => $placeId,
                            'attempt' => $retries,
                            'max' => $maxRetries
                        ]);
                        sleep($retryDelay);
                    }
                }
            }

            // Progress reporting every 10 restaurants
            if (($index + 1) % 10 === 0) {
                $this->logger->info('Progress update', [
                    'fetched' => $index + 1,
                    'total' => count($places),
                    'percent' => round((($index + 1) / count($places)) * 100, 1)
                ]);
            }
        }

        return $detailed;
    }

    /**
     * Import restaurants to database
     */
    private function importRestaurants(array $restaurants): void
    {
        foreach ($restaurants as $restaurant) {
            try {
                $id = $this->db->upsertRestaurant($restaurant);
                
                if ($id) {
                    $this->stats['imported']++;
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->logger->error('Failed to import restaurant', [
                    'name' => $restaurant['name'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Cleanup tasks
     */
    private function cleanup(): void
    {
        // Mark old restaurants as potentially inactive
        $staleRestaurants = $this->db->getStaleRestaurants(30);
        
        foreach ($staleRestaurants as $restaurant) {
            $this->logger->info('Found stale restaurant', [
                'name' => $restaurant['name'],
                'last_verified' => $restaurant['last_verified']
            ]);
            $this->stats['stale']++;
        }

        // Get final statistics
        $dbStats = $this->db->getStats();
        $this->stats = array_merge($this->stats, $dbStats);
    }

    /**
     * Verify specific restaurant by Place ID
     */
    public function verifyRestaurant(string $placeId): array
    {
        $this->logger->info('Verifying restaurant', ['place_id' => $placeId]);

        try {
            $details = $this->placesClient->getPlaceDetails($placeId);
            $transformed = $this->transformer->transformPlace($details);
            $id = $this->db->upsertRestaurant($transformed);

            $this->logger->info('Restaurant verified', [
                'place_id' => $placeId,
                'id' => $id,
                'name' => $transformed['name']
            ]);

            return ['success' => true, 'id' => $id, 'name' => $transformed['name']];

        } catch (\Exception $e) {
            $this->logger->error('Restaurant verification failed', [
                'place_id' => $placeId,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get sync statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, $this->db->getStats());
    }

    private function resetStats(): void
    {
        $this->stats = [
            'discovered' => 0,
            'detailed' => 0,
            'transformed' => 0,
            'imported' => 0,
            'errors' => 0,
            'stale' => 0,
            'duration' => 0,
            'success' => false,
        ];
    }
}
