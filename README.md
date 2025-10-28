# eKaty Restaurant Database Maintenance Agent

🤖 Autonomous PHP agent for maintaining 100% uptime and data accuracy of the eKaty restaurant database using Google Places API.

## Features

✅ **Automated Daily Sync** - Scheduled updates from Google Places API  
✅ **Zero Downtime** - Continuous operation without service interruption  
✅ **Complete Coverage** - All restaurants in Katy, TX (15km radius)  
✅ **Data Accuracy** - Real-time verification of business status, hours, photos  
✅ **Error Recovery** - Automatic retry and failure handling  
✅ **Audit Trail** - Complete logging of all sync operations  
✅ **CLI Interface** - Easy manual control and monitoring  
✅ **Health Checks** - System monitoring and alerting

## Quick Start

### Prerequisites

- PHP 8.1 or higher
- Composer
- Google Places API key
- SQLite (default) or PostgreSQL

### Installation

```bash
# Clone repository
git clone <your-repo-url> ekaty-agent
cd ekaty-agent

# Install dependencies
composer install

# Copy environment configuration
cp .env.example .env

# Edit .env and add your Google API key
# GOOGLE_PLACES_API_KEY=your_api_key_here
```

### Configuration

Edit `.env` file with your settings:

```env
# Google Places API
GOOGLE_PLACES_API_KEY=your_api_key_here

# Database (SQLite by default)
DB_PATH=./data/ekaty.db

# Location (Katy, TX)
LOCATION_LAT=29.7858
LOCATION_LNG=-95.8245
SEARCH_RADIUS=15000

# Monitoring
ALERT_EMAIL=james@ekaty.com
LOG_LEVEL=INFO
```

### Usage

```bash
# Run full sync
php bin/agent sync

# Check system health
php bin/agent health

# View database statistics
php bin/agent stats

# Verify specific restaurant
php bin/agent verify <google-place-id>

# Force sync (bypass config check)
php bin/agent sync --force

# Dry run (no database changes)
php bin/agent sync --dry-run
```

## Commands Reference

### `sync`
Synchronize all restaurants from Google Places API

**Options:**
- `--force, -f` - Force sync even if disabled in config
- `--dry-run` - Run without making database changes

**Example:**
```bash
php bin/agent sync
php bin/agent sync --force
php bin/agent sync --dry-run
```

### `stats`
Display database statistics and stale restaurants

```bash
php bin/agent stats
```

**Output:**
- Total restaurants
- Active/inactive counts
- Average rating
- Restaurants needing verification

### `verify`
Verify and update a specific restaurant

```bash
php bin/agent verify ChIJN1t_tDeuEmsRUsoyG83frY4
```

### `health`
Check system health and connectivity

```bash
php bin/agent health
```

**Checks:**
- PHP version
- Database connection
- Google API key configuration
- Google API connectivity
- Disk space

## Automated Scheduling

### Cron Setup (Linux/Mac)

Add to crontab (`crontab -e`):

```cron
# Run sync daily at 3:00 AM CST
0 3 * * * cd /path/to/ekaty-agent && php bin/agent sync >> /var/log/ekaty-sync.log 2>&1

# Health check every hour
0 * * * * cd /path/to/ekaty-agent && php bin/agent health >> /var/log/ekaty-health.log 2>&1
```

### Windows Task Scheduler

```powershell
# Create scheduled task
schtasks /create /tn "eKaty Sync" /tr "php C:\path\to\ekaty-agent\bin\agent sync" /sc daily /st 03:00

# Create health check task
schtasks /create /tn "eKaty Health" /tr "php C:\path\to\ekaty-agent\bin\agent health" /sc hourly
```

## Fly.io Deployment

### 1. Create Fly.io Configuration

Create `fly.toml`:

```toml
app = "ekaty-agent"
primary_region = "iad"

[build]
  [build.args]
    PHP_VERSION = "8.2"

[env]
  APP_ENV = "production"
  TIMEZONE = "America/Chicago"

[mounts]
  source = "ekaty_data"
  destination = "/app/data"

[[services]]
  internal_port = 8080
  protocol = "tcp"

  [[services.ports]]
    port = 80
    handlers = ["http"]

  [[services.ports]]
    port = 443
    handlers = ["tls", "http"]

# Cron job configuration
[processes]
  sync = "php bin/agent sync"

[[cron]]
  schedule = "0 3 * * *"
  cmd = "php bin/agent sync"
```

### 2. Create Dockerfile

```dockerfile
FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pdo pdo_sqlite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Create data directory
RUN mkdir -p /app/data /app/logs

# Make CLI executable
RUN chmod +x /app/bin/agent

CMD ["php", "bin/agent", "sync"]
```

### 3. Deploy to Fly.io

```bash
# Login to Fly.io
fly auth login

# Create app
fly launch

# Set secrets
fly secrets set GOOGLE_PLACES_API_KEY=your_api_key_here

# Deploy
fly deploy

# Create persistent volume for database
fly volumes create ekaty_data --size 1

# Check status
fly status

# View logs
fly logs
```

### 4. Monitor on Fly.io

```bash
# View logs
fly logs

# SSH into container
fly ssh console

# Run manual sync
fly ssh console -C "php bin/agent sync"

# Check health
fly ssh console -C "php bin/agent health"
```

## Database Schema

### Restaurants Table

| Field | Type | Description |
|-------|------|-------------|
| id | TEXT | Unique identifier |
| name | TEXT | Restaurant name |
| slug | TEXT | URL-friendly slug |
| address | TEXT | Full address |
| latitude | REAL | Latitude coordinate |
| longitude | REAL | Longitude coordinate |
| phone | TEXT | Phone number |
| website | TEXT | Website URL |
| hours | TEXT | Opening hours (JSON) |
| rating | REAL | Average rating |
| source_id | TEXT | Google Place ID |
| last_verified | TEXT | Last sync timestamp |
| active | INTEGER | Operational status |

### Audit Logs Table

| Field | Type | Description |
|-------|------|-------------|
| id | TEXT | Unique identifier |
| entity | TEXT | Entity type |
| action | TEXT | Action performed |
| changes | TEXT | Changes (JSON) |
| created_at | TEXT | Timestamp |

## Monitoring & Alerts

### Email Alerts

Configure email alerts for critical errors:

```env
ALERT_EMAIL=james@ekaty.com
```

### Webhook Alerts

Configure webhook for Slack/Discord/etc:

```env
ALERT_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### Logs

Logs are stored in `./logs/agent.log` with automatic rotation.

**Log Levels:**
- DEBUG - Detailed debugging information
- INFO - General informational messages
- WARNING - Warning messages
- ERROR - Error conditions
- CRITICAL - Critical conditions

## Troubleshooting

### API Quota Exceeded

```bash
# Check current stats
php bin/agent stats

# Verify single restaurant instead of full sync
php bin/agent verify <place-id>
```

### Database Locked

```bash
# Check database file permissions
ls -la data/ekaty.db

# Fix permissions
chmod 644 data/ekaty.db
```

### Connection Errors

```bash
# Run health check
php bin/agent health

# Test API connectivity
php bin/agent verify ChIJN1t_tDeuEmsRUsoyG83frY4
```

## Architecture

```
┌─────────────────────────────────────────┐
│         CLI Application (bin/agent)     │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│         Sync Engine                     │
│  - Discover restaurants                 │
│  - Fetch details                        │
│  - Transform data                       │
│  - Import to database                   │
└─────────────────┬───────────────────────┘
                  │
        ┌─────────┴──────────┐
        ▼                    ▼
┌───────────────┐   ┌──────────────────┐
│ Google Places │   │ SQLite Database  │
│     API       │   │                  │
└───────────────┘   └──────────────────┘
```

## Development

### Run Tests

```bash
composer test
```

### Code Style

```bash
composer cs-fix
```

### Development Mode

```env
APP_ENV=development
APP_DEBUG=true
LOG_LEVEL=DEBUG
```

## API Limits

**Google Places API:**
- Nearby Search: 60 requests per minute
- Place Details: 100 requests per minute
- Daily quota: Check your Google Cloud Console

**Rate Limiting:**
Configured via `RATE_LIMIT_DELAY` (milliseconds between requests)

## Support

- **Email:** james@ekaty.com
- **Issues:** GitHub Issues
- **Logs:** `./logs/agent.log`

## License

Proprietary - eKaty.com

## Version

1.0.0 - Initial Release

---

**Maintainer:** james@ekaty.com  
**Last Updated:** 2025-10-28
