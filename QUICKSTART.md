# Quick Start Guide - Docker

Get the eKaty Restaurant Agent running in Docker in 5 minutes.

## Prerequisites

- Docker and Docker Compose installed
- Google Places API key ([Get one here](https://console.cloud.google.com/))

## 1. Clone Repository

```bash
git clone https://github.com/Swimhack/ekaty-db-agent.git
cd ekaty-db-agent
```

## 2. Setup Environment Variables

```bash
# Copy Docker environment template
cp .env.docker .env

# Edit .env and add your API key
# On Windows:
notepad .env

# On Mac/Linux:
nano .env
```

**Required:** Replace `your_api_key_here` with your actual Google Places API key:
```
GOOGLE_PLACES_API_KEY=AIzaSyC...your_actual_key
```

## 3. Run with Docker Compose

```bash
# Build and start
docker-compose up -d

# View logs
docker-compose logs -f ekaty-agent
```

## 4. Verify It's Working

```bash
# Check health
docker-compose exec ekaty-agent php bin/agent health

# View statistics
docker-compose exec ekaty-agent php bin/agent stats

# Run manual sync
docker-compose exec ekaty-agent php bin/agent sync
```

## 5. Access Your Data

The SQLite database is stored in `./data/ekaty.db` on your host machine.

```bash
# View database directly
sqlite3 data/ekaty.db "SELECT COUNT(*) FROM restaurants;"

# Or use a DB viewer like DB Browser for SQLite
```

## Automated Daily Sync

The container automatically runs a sync every day at 3:00 AM (configured in Dockerfile with cron).

## Stopping the Agent

```bash
# Stop container
docker-compose down

# Stop and remove data volumes
docker-compose down -v
```

## Troubleshooting

### Container won't start

```bash
# Check logs
docker-compose logs

# Rebuild from scratch
docker-compose build --no-cache
docker-compose up -d
```

### API key not working

```bash
# Verify API key is set
docker-compose exec ekaty-agent printenv | grep GOOGLE_PLACES_API_KEY

# Test API connectivity
docker-compose exec ekaty-agent php bin/agent health
```

### Permission issues

```bash
# Fix data directory permissions (Mac/Linux)
sudo chown -R $USER:$USER data logs

# Windows: Run Docker Desktop as Administrator
```

## Production Deployment

For production, use Docker secrets instead of .env files:

```bash
# Create secret
echo "your_api_key" | docker secret create google_api_key -

# Update docker-compose.yml to use secrets
# See DOCKER.md for full instructions
```

## Next Steps

- Read [DOCKER.md](DOCKER.md) for advanced Docker configuration
- Read [README.md](README.md) for full documentation
- Set up monitoring with alerts (see .env ALERT_EMAIL and ALERT_WEBHOOK_URL)

## Support

Issues? Check [GitHub Issues](https://github.com/Swimhack/ekaty-db-agent/issues) or email james@ekaty.com
