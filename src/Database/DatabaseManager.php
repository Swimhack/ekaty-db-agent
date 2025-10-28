<?php

namespace EkatyAgent\Database;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

class DatabaseManager
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private string $dbType;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->dbType = $config['type'] ?? 'sqlite';
        $this->pdo = $this->createConnection($config);
        $this->initializeSchema();
    }

    private function createConnection(array $config): PDO
    {
        try {
            if ($config['type'] === 'sqlite') {
                $dbPath = $config['path'] ?? './data/ekaty.db';
                $dbDir = dirname($dbPath);
                
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }

                $dsn = "sqlite:$dbPath";
                $pdo = new PDO($dsn);
                
                $this->logger->info('Connected to SQLite', ['path' => $dbPath]);

            } else if ($config['type'] === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    $config['host'],
                    $config['port'] ?? 5432,
                    $config['name']
                );
                
                $pdo = new PDO(
                    $dsn,
                    $config['user'],
                    $config['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $this->logger->info('Connected to PostgreSQL', [
                    'host' => $config['host'],
                    'database' => $config['name']
                ]);
            } else {
                throw new \InvalidArgumentException("Unsupported database type: {$config['type']}");
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;

        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function initializeSchema(): void
    {
        $this->logger->info('Initializing database schema');

        // Restaurants table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS restaurants (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL,
                description TEXT,
                address TEXT NOT NULL,
                city TEXT DEFAULT 'Katy',
                state TEXT DEFAULT 'TX',
                zip_code TEXT,
                latitude REAL NOT NULL,
                longitude REAL NOT NULL,
                phone TEXT,
                website TEXT,
                email TEXT,
                categories TEXT,
                cuisine_types TEXT,
                hours TEXT,
                price_level TEXT DEFAULT 'MODERATE',
                photos TEXT,
                logo_url TEXT,
                featured INTEGER DEFAULT 0,
                verified INTEGER DEFAULT 0,
                active INTEGER DEFAULT 1,
                rating REAL,
                review_count INTEGER DEFAULT 0,
                source TEXT DEFAULT 'google_places',
                source_id TEXT UNIQUE,
                metadata TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                last_verified TEXT
            )
        ");

        // Audit log table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id TEXT PRIMARY KEY,
                entity TEXT NOT NULL,
                entity_id TEXT NOT NULL,
                action TEXT NOT NULL,
                user_id TEXT,
                changes TEXT,
                metadata TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Indexes
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_restaurants_source_id ON restaurants(source_id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_restaurants_active ON restaurants(active)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_restaurants_last_verified ON restaurants(last_verified)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_entity ON audit_logs(entity, entity_id)");

        $this->logger->info('Database schema initialized');
    }

    public function upsertRestaurant(array $data): string
    {
        $sourceId = $data['source_id'] ?? null;
        
        if (!$sourceId) {
            throw new \InvalidArgumentException('source_id is required');
        }

        // Check if restaurant exists
        $existing = $this->findRestaurantBySourceId($sourceId);

        if ($existing) {
            return $this->updateRestaurant($existing['id'], $data);
        } else {
            return $this->insertRestaurant($data);
        }
    }

    private function insertRestaurant(array $data): string
    {
        $id = $this->generateId();
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO restaurants (
            id, name, slug, description, address, city, state, zip_code,
            latitude, longitude, phone, website, categories, cuisine_types,
            hours, price_level, photos, rating, review_count, source, source_id,
            metadata, active, created_at, updated_at, last_verified
        ) VALUES (
            :id, :name, :slug, :description, :address, :city, :state, :zip_code,
            :latitude, :longitude, :phone, :website, :categories, :cuisine_types,
            :hours, :price_level, :photos, :rating, :review_count, :source, :source_id,
            :metadata, :active, :created_at, :updated_at, :last_verified
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'] ?? 'Katy',
            'state' => $data['state'] ?? 'TX',
            'zip_code' => $data['zip_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'categories' => $data['categories'] ?? null,
            'cuisine_types' => $data['cuisine_types'] ?? null,
            'hours' => $data['hours'] ?? null,
            'price_level' => $data['price_level'] ?? 'MODERATE',
            'photos' => $data['photos'] ?? null,
            'rating' => $data['rating'] ?? null,
            'review_count' => $data['review_count'] ?? 0,
            'source' => $data['source'] ?? 'google_places',
            'source_id' => $data['source_id'],
            'metadata' => $data['metadata'] ?? null,
            'active' => $data['active'] ?? 1,
            'created_at' => $now,
            'updated_at' => $now,
            'last_verified' => $now
        ]);

        $this->logger->debug('Restaurant inserted', ['id' => $id, 'name' => $data['name']]);

        return $id;
    }

    private function updateRestaurant(string $id, array $data): string
    {
        $now = date('Y-m-d H:i:s');

        $sql = "UPDATE restaurants SET
            name = :name,
            description = :description,
            address = :address,
            city = :city,
            state = :state,
            zip_code = :zip_code,
            latitude = :latitude,
            longitude = :longitude,
            phone = :phone,
            website = :website,
            categories = :categories,
            cuisine_types = :cuisine_types,
            hours = :hours,
            price_level = :price_level,
            photos = :photos,
            rating = :rating,
            review_count = :review_count,
            metadata = :metadata,
            active = :active,
            updated_at = :updated_at,
            last_verified = :last_verified
            WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'address' => $data['address'],
            'city' => $data['city'] ?? 'Katy',
            'state' => $data['state'] ?? 'TX',
            'zip_code' => $data['zip_code'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'categories' => $data['categories'] ?? null,
            'cuisine_types' => $data['cuisine_types'] ?? null,
            'hours' => $data['hours'] ?? null,
            'price_level' => $data['price_level'] ?? 'MODERATE',
            'photos' => $data['photos'] ?? null,
            'rating' => $data['rating'] ?? null,
            'review_count' => $data['review_count'] ?? 0,
            'metadata' => $data['metadata'] ?? null,
            'active' => $data['active'] ?? 1,
            'updated_at' => $now,
            'last_verified' => $now
        ]);

        $this->logger->debug('Restaurant updated', ['id' => $id, 'name' => $data['name']]);

        return $id;
    }

    public function findRestaurantBySourceId(string $sourceId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM restaurants WHERE source_id = :source_id LIMIT 1");
        $stmt->execute(['source_id' => $sourceId]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    public function logAudit(string $entity, string $entityId, string $action, ?array $changes = null, ?array $metadata = null): void
    {
        $sql = "INSERT INTO audit_logs (id, entity, entity_id, action, changes, metadata, created_at)
                VALUES (:id, :entity, :entity_id, :action, :changes, :metadata, :created_at)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $this->generateId(),
            'entity' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'changes' => $changes ? json_encode($changes) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getStaleRestaurants(int $daysOld = 7): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-$daysOld days"));
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM restaurants 
            WHERE last_verified < :cutoff OR last_verified IS NULL
            ORDER BY last_verified ASC
        ");
        
        $stmt->execute(['cutoff' => $cutoff]);
        
        return $stmt->fetchAll();
    }

    public function getStats(): array
    {
        $stats = [];

        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM restaurants");
        $stats['total'] = $stmt->fetch()['total'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as active FROM restaurants WHERE active = 1");
        $stats['active'] = $stmt->fetch()['active'];

        $stmt = $this->pdo->query("SELECT COUNT(*) as inactive FROM restaurants WHERE active = 0");
        $stats['inactive'] = $stmt->fetch()['inactive'];

        $stmt = $this->pdo->query("SELECT AVG(rating) as avg_rating FROM restaurants WHERE rating IS NOT NULL");
        $stats['avg_rating'] = round($stmt->fetch()['avg_rating'] ?? 0, 2);

        return $stats;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(12));
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
