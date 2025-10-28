<?php

namespace EkatyAgent\Transform;

use Psr\Log\LoggerInterface;

class DataTransformer
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Transform Google Place data to eKaty restaurant schema
     */
    public function transformPlace(array $place): array
    {
        try {
            $addressComponents = $this->parseAddressComponents($place['address_components'] ?? []);
            
            $transformed = [
                'name' => $place['name'] ?? 'Unknown',
                'slug' => $this->generateSlug($place['name'] ?? 'unknown'),
                'description' => $this->generateDescription($place),
                'address' => $place['formatted_address'] ?? '',
                'city' => $addressComponents['city'] ?? 'Katy',
                'state' => $addressComponents['state'] ?? 'TX',
                'zip_code' => $addressComponents['zip'] ?? null,
                'latitude' => $place['geometry']['location']['lat'] ?? 0,
                'longitude' => $place['geometry']['location']['lng'] ?? 0,
                'phone' => $place['formatted_phone_number'] ?? null,
                'website' => $place['website'] ?? null,
                'categories' => $this->extractCategories($place['types'] ?? []),
                'cuisine_types' => $this->extractCuisineTypes($place['types'] ?? []),
                'hours' => $this->transformHours($place['opening_hours'] ?? null),
                'price_level' => $this->transformPriceLevel($place['price_level'] ?? null),
                'photos' => $this->extractPhotoUrls($place['photos'] ?? []),
                'rating' => $place['rating'] ?? null,
                'review_count' => $place['user_ratings_total'] ?? 0,
                'source' => 'google_places',
                'source_id' => $place['place_id'] ?? null,
                'metadata' => json_encode($this->extractMetadata($place)),
                'active' => $this->isBusinessActive($place['business_status'] ?? null),
            ];

            $this->logger->debug('Transformed place data', [
                'name' => $transformed['name'],
                'source_id' => $transformed['source_id']
            ]);

            return $transformed;

        } catch (\Exception $e) {
            $this->logger->error('Transform failed', [
                'error' => $e->getMessage(),
                'place_id' => $place['place_id'] ?? 'unknown'
            ]);
            throw $e;
        }
    }

    private function parseAddressComponents(array $components): array
    {
        $parsed = [
            'street_number' => null,
            'route' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
        ];

        foreach ($components as $component) {
            $types = $component['types'] ?? [];
            
            if (in_array('street_number', $types)) {
                $parsed['street_number'] = $component['long_name'];
            }
            if (in_array('route', $types)) {
                $parsed['route'] = $component['long_name'];
            }
            if (in_array('locality', $types)) {
                $parsed['city'] = $component['long_name'];
            }
            if (in_array('administrative_area_level_1', $types)) {
                $parsed['state'] = $component['short_name'];
            }
            if (in_array('postal_code', $types)) {
                $parsed['zip'] = $component['long_name'];
            }
        }

        return $parsed;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Add random suffix to ensure uniqueness
        $slug .= '-' . substr(md5(uniqid()), 0, 6);
        
        return $slug;
    }

    private function generateDescription(array $place): ?string
    {
        $types = $place['types'] ?? [];
        $cuisines = $this->extractCuisineTypes($types);
        $name = $place['name'] ?? 'This restaurant';
        
        if ($cuisines) {
            $cuisineList = implode(', ', array_slice(explode(',', $cuisines), 0, 2));
            return "$name offers $cuisineList cuisine in Katy, Texas.";
        }
        
        return "$name is a restaurant located in Katy, Texas.";
    }

    private function extractCategories(array $types): string
    {
        $categoryMap = [
            'restaurant' => 'Restaurant',
            'food' => 'Food',
            'cafe' => 'Cafe',
            'bar' => 'Bar',
            'meal_delivery' => 'Delivery',
            'meal_takeaway' => 'Takeaway',
            'bakery' => 'Bakery',
            'point_of_interest' => 'Point of Interest',
        ];

        $categories = [];
        foreach ($types as $type) {
            if (isset($categoryMap[$type])) {
                $categories[] = $categoryMap[$type];
            }
        }

        return implode(',', array_unique($categories)) ?: 'Restaurant';
    }

    private function extractCuisineTypes(array $types): string
    {
        $cuisineMap = [
            'american_restaurant' => 'American',
            'chinese_restaurant' => 'Chinese',
            'italian_restaurant' => 'Italian',
            'japanese_restaurant' => 'Japanese',
            'mexican_restaurant' => 'Mexican',
            'indian_restaurant' => 'Indian',
            'thai_restaurant' => 'Thai',
            'french_restaurant' => 'French',
            'greek_restaurant' => 'Greek',
            'mediterranean_restaurant' => 'Mediterranean',
            'seafood_restaurant' => 'Seafood',
            'steakhouse' => 'Steakhouse',
            'pizza_restaurant' => 'Pizza',
            'sushi_restaurant' => 'Sushi',
            'barbecue_restaurant' => 'BBQ',
            'fast_food_restaurant' => 'Fast Food',
            'sandwich_shop' => 'Sandwiches',
            'cafe' => 'Cafe',
            'bakery' => 'Bakery',
            'vegetarian_restaurant' => 'Vegetarian',
        ];

        $cuisines = [];
        foreach ($types as $type) {
            if (isset($cuisineMap[$type])) {
                $cuisines[] = $cuisineMap[$type];
            }
        }

        return implode(',', array_unique($cuisines));
    }

    private function transformHours(?array $openingHours): ?string
    {
        if (!$openingHours || !isset($openingHours['periods'])) {
            return null;
        }

        $hours = [];
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($openingHours['periods'] as $period) {
            $day = $period['open']['day'] ?? null;
            if ($day === null) continue;

            $dayName = $dayNames[$day];
            $open = $period['open']['time'] ?? null;
            $close = $period['close']['time'] ?? null;

            if ($open && $close) {
                $hours[$dayName] = [
                    'open' => $this->formatTime($open),
                    'close' => $this->formatTime($close),
                ];
            } else if ($open) {
                $hours[$dayName] = ['open' => '24 hours'];
            }
        }

        return json_encode($hours);
    }

    private function formatTime(string $time): string
    {
        if (strlen($time) !== 4) return $time;
        
        $hour = (int) substr($time, 0, 2);
        $min = substr($time, 2, 2);
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour = $hour % 12 ?: 12;
        
        return "$hour:$min $ampm";
    }

    private function transformPriceLevel(?int $priceLevel): string
    {
        return match($priceLevel) {
            1 => 'BUDGET',
            2 => 'MODERATE',
            3 => 'EXPENSIVE',
            4 => 'LUXURY',
            default => 'MODERATE',
        };
    }

    private function extractPhotoUrls(array $photos): ?string
    {
        if (empty($photos)) {
            return null;
        }

        $urls = [];
        foreach (array_slice($photos, 0, 10) as $photo) {
            if (isset($photo['photo_reference'])) {
                // Store photo reference, will be converted to URL when needed
                $urls[] = $photo['photo_reference'];
            }
        }

        return implode(',', $urls);
    }

    private function isBusinessActive(?string $status): int
    {
        return ($status === 'OPERATIONAL' || $status === null) ? 1 : 0;
    }

    private function extractMetadata(array $place): array
    {
        return [
            'google_url' => $place['url'] ?? null,
            'place_id' => $place['place_id'] ?? null,
            'types' => $place['types'] ?? [],
            'business_status' => $place['business_status'] ?? null,
            'utc_offset' => $place['utc_offset'] ?? null,
            'vicinity' => $place['vicinity'] ?? null,
            'permanently_closed' => $place['permanently_closed'] ?? false,
            'reviews' => array_slice($place['reviews'] ?? [], 0, 5), // Store first 5 reviews
        ];
    }

    /**
     * Batch transform multiple places
     */
    public function transformPlaces(array $places): array
    {
        $transformed = [];
        $errors = 0;

        foreach ($places as $place) {
            try {
                $transformed[] = $this->transformPlace($place);
            } catch (\Exception $e) {
                $errors++;
                $this->logger->warning('Skipped place due to transform error', [
                    'place_id' => $place['place_id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->logger->info('Batch transform complete', [
            'total' => count($places),
            'success' => count($transformed),
            'errors' => $errors
        ]);

        return $transformed;
    }
}
