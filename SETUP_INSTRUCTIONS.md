# Setup Instructions - Restaurant Data Sync

## What Has Been Done

✅ **Environment Setup**
- Created `.env` configuration file from `.env.example`
- Created `data/` and `logs/` directories
- Installed SQLite PDO extension for PHP 8.4
- Installed all Composer dependencies

✅ **Database**
- SQLite database initialized at `./data/ekaty.db`
- Database schema created (restaurants and audit_logs tables)
- Database connection verified and working

✅ **Scripts Created**
- `setup-and-sync.sh` - Interactive script to configure API key and run sync
- `setup-cron.sh` - Script to install automated daily sync cron job

## What's Needed to Run the Sync

### 1. Google Places API Key

The application needs a valid Google Places API key to fetch restaurant data.

**Option A: Configure in .env file**
```bash
# Edit .env file and replace the placeholder
nano .env

# Change this line:
GOOGLE_PLACES_API_KEY=your_api_key_here

# To your actual API key:
GOOGLE_PLACES_API_KEY=AIzaSy...your...actual...key
```

**Option B: Use the setup script**
```bash
./setup-and-sync.sh
# The script will prompt you to enter your API key
```

**Option C: Set environment variable**
```bash
export GOOGLE_PLACES_API_KEY="your_actual_api_key"
php bin/agent sync
```

### 2. Get an API Key

If you don't have a Google Places API key:

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the "Places API (New)" or "Places API"
4. Go to "Credentials" → "Create Credentials" → "API Key"
5. Copy the API key
6. (Recommended) Restrict the API key to only allow Places API requests

## Running the Sync

### Manual Sync

```bash
# Run a one-time sync
php bin/agent sync

# View database statistics
php bin/agent stats

# Check system health
php bin/agent health
```

### Automated Sync with Cron

```bash
# Install cron job (runs daily at 3:00 AM)
./setup-cron.sh

# Or manually add to crontab
crontab -e
# Add this line:
# 0 3 * * * cd /home/user/ekaty-db-agent && php bin/agent sync >> logs/cron.log 2>&1
```

## Current Status

**System Health:**
- ✅ PHP Version: 8.4.14
- ✅ Database Connection: Working
- ✅ SQLite PDO Extension: Installed
- ⚠️  Google API Key: Not configured
- ⚠️  Google API Access: Waiting for API key

**Next Steps:**
1. Configure Google Places API key in `.env`
2. Run `php bin/agent sync` to load restaurant data
3. Install cron job with `./setup-cron.sh` for daily automated syncs

## Testing Without API Key

If you want to verify the setup without running the actual sync, you can run:

```bash
# This will show the system health status
php bin/agent health

# This will show database statistics (will be empty initially)
php bin/agent stats
```

## Deployment Notes

This application is designed to run:
- **Locally** (current setup) - with manual sync or cron
- **Docker** - using the provided Dockerfile and docker-compose.yml
- **Fly.io** - using the fly.toml configuration

For production deployment on Fly.io, set the API key as a secret:
```bash
fly secrets set GOOGLE_PLACES_API_KEY=your_api_key_here
```

## Log Files

Logs are stored in the `logs/` directory:
- `logs/cron.log` - Output from cron jobs
- Application logs include timestamps and error details

## Troubleshooting

**Issue: "API key not configured"**
- Solution: Update GOOGLE_PLACES_API_KEY in .env file

**Issue: "could not find driver"**
- Solution: Already fixed! SQLite PDO extension has been installed

**Issue: "Database connection failed"**
- Solution: Already fixed! Database is working correctly

**Issue: 403 Forbidden from Google API**
- Solution: Use a valid Google Places API key with proper permissions
