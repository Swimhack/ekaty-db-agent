<?php

namespace EkatyAgent\Google;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class PlacesClient
{
    private Client $client;
    private string $apiKey;
    private LoggerInterface $logger;
    private int $rateLimitDelay;

    // Google Places API endpoints
    private const BASE_URL = 'https://maps.googleapis.com/maps/api/place';
    private const NEARBY_SEARCH = '/nearbysearch/json';
    private const PLACE_DETAILS = '/details/json';
    private const PHOTO = '/photo';

    public function __construct(
        string $apiKey,
        LoggerInterface $logger,
        int $rateLimitDelay = 100
    ) {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->rateLimitDelay = $rateLimitDelay;
        
        $this->client = new Client([
            'base_uri' => self::BASE_URL,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
            ]
        ]);
    }

    /**
     * Search for restaurants near a location
     */
    public function nearbySearch(
        float $lat,
        float $lng,
        int $radius = 15000,
        string $type = 'restaurant',
        ?string $pageToken = null
    ): array {
        $params = [
            'location' => "$lat,$lng",
            'radius' => $radius,
            'type' => $type,
            'key' => $this->apiKey,
        ];

        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
            // Google requires a short delay between paginated requests
            sleep(2);
        }

        try {
            $this->logger->info('Nearby search', [
                'lat' => $lat,
                'lng' => $lng,
                'radius' => $radius,
                'has_page_token' => $pageToken !== null
            ]);

            $response = $this->client->get(self::NEARBY_SEARCH, [
                'query' => $params
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                throw new \RuntimeException("Places API error: {$data['status']}");
            }

            usleep($this->rateLimitDelay * 1000); // Rate limiting

            return $data;

        } catch (GuzzleException $e) {
            $this->logger->error('Nearby search failed', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng
            ]);
            throw $e;
        }
    }

    /**
     * Get detailed information about a place
     */
    public function getPlaceDetails(string $placeId): array
    {
        $params = [
            'place_id' => $placeId,
            'fields' => implode(',', [
                'place_id',
                'name',
                'formatted_address',
                'address_components',
                'geometry',
                'formatted_phone_number',
                'international_phone_number',
                'website',
                'business_status',
                'opening_hours',
                'price_level',
                'rating',
                'user_ratings_total',
                'types',
                'photos',
                'reviews',
                'url'
            ]),
            'key' => $this->apiKey,
        ];

        try {
            $this->logger->debug('Fetching place details', ['place_id' => $placeId]);

            $response = $this->client->get(self::PLACE_DETAILS, [
                'query' => $params
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($data['status'] !== 'OK') {
                throw new \RuntimeException("Place details error: {$data['status']}");
            }

            usleep($this->rateLimitDelay * 1000); // Rate limiting

            return $data['result'] ?? [];

        } catch (GuzzleException $e) {
            $this->logger->error('Place details failed', [
                'error' => $e->getMessage(),
                'place_id' => $placeId
            ]);
            throw $e;
        }
    }

    /**
     * Get photo URL for a photo reference
     */
    public function getPhotoUrl(string $photoReference, int $maxWidth = 800): string
    {
        return self::BASE_URL . self::PHOTO . '?' . http_build_query([
            'photoreference' => $photoReference,
            'maxwidth' => $maxWidth,
            'key' => $this->apiKey
        ]);
    }

    /**
     * Search all restaurants in area using multiple search points
     */
    public function searchAllRestaurants(
        float $centerLat,
        float $centerLng,
        int $radius = 15000
    ): array {
        $allPlaces = [];
        $seenPlaceIds = [];

        // Generate 6 search points for complete coverage
        $searchPoints = $this->generateSearchPoints($centerLat, $centerLng, $radius);

        $this->logger->info('Starting comprehensive search', [
            'center' => "$centerLat,$centerLng",
            'radius' => $radius,
            'search_points' => count($searchPoints)
        ]);

        foreach ($searchPoints as $index => $point) {
            $this->logger->info("Searching point " . ($index + 1), $point);
            
            $pageToken = null;
            do {
                $result = $this->nearbySearch(
                    $point['lat'],
                    $point['lng'],
                    $radius,
                    'restaurant',
                    $pageToken
                );

                if (isset($result['results'])) {
                    foreach ($result['results'] as $place) {
                        $placeId = $place['place_id'];
                        
                        // Deduplicate
                        if (!isset($seenPlaceIds[$placeId])) {
                            $allPlaces[] = $place;
                            $seenPlaceIds[$placeId] = true;
                        }
                    }
                }

                $pageToken = $result['next_page_token'] ?? null;

            } while ($pageToken);
        }

        $this->logger->info('Search complete', [
            'total_restaurants' => count($allPlaces),
            'search_points_used' => count($searchPoints)
        ]);

        return $allPlaces;
    }

    /**
     * Generate search points for complete area coverage
     */
    private function generateSearchPoints(float $lat, float $lng, int $radius): array
    {
        $points = [['lat' => $lat, 'lng' => $lng]]; // Center point

        // Calculate offset for 60% coverage overlap
        $offset = $radius * 0.0000089 * 0.8; // Approximate degrees per meter

        // Surrounding points in hexagonal pattern
        $angles = [0, 60, 120, 180, 240, 300];
        foreach ($angles as $angle) {
            $rad = deg2rad($angle);
            $points[] = [
                'lat' => $lat + ($offset * cos($rad)),
                'lng' => $lng + ($offset * sin($rad) / cos(deg2rad($lat)))
            ];
        }

        return $points;
    }
}
