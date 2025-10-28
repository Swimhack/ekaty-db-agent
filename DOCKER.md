# Docker Deployment Guide

## Quick Start with Docker

### 1. Setup Environment Variables

```bash
# Copy the Docker environment template
cp .env.docker .env

# Edit .env and add your Google Places API key
# GOOGLE_PLACES_API_KEY=your_actual_api_key
```

### 2. Build and Run with Docker Compose

```bash
# Build and start the container
docker-compose up -d

# View logs
docker-compose logs -f

# Check health
docker-compose exec ekaty-agent php bin/agent health

# Run manual sync
docker-compose exec ekaty-agent php bin/agent sync

# Stop the container
docker-compose down
```

### 3. Alternative: Docker Run

```bash
# Build image
docker build -t ekaty-agent:latest .

# Run container
docker run -d \
  --name ekaty-agent \
  --env-file .env \
  -v $(pwd)/data:/app/data \
  -v $(pwd)/logs:/app/logs \
  --restart unless-stopped \
  ekaty-agent:latest

# View logs
docker logs -f ekaty-agent

# Execute commands
docker exec ekaty-agent php bin/agent health
docker exec ekaty-agent php bin/agent sync
```

## Environment Variable Best Practices

### Option 1: .env File (Recommended for Development)

```bash
# Create .env file
cp .env.docker .env
# Edit and add secrets
nano .env

# Use with docker-compose
docker-compose up -d
```

### Option 2: Docker Secrets (Production)

```bash
# Create secrets
echo "your_api_key" | docker secret create google_api_key -

# Use in docker-compose.yml
version: '3.8'
services:
  ekaty-agent:
    secrets:
      - google_api_key
    environment:
      - GOOGLE_PLACES_API_KEY_FILE=/run/secrets/google_api_key

secrets:
  google_api_key:
    external: true
```

### Option 3: Environment Variables Only

```bash
docker run -d \
  --name ekaty-agent \
  -e GOOGLE_PLACES_API_KEY="your_key" \
  -e LOCATION_LAT="29.7858" \
  -e LOCATION_LNG="-95.8245" \
  -e ALERT_EMAIL="james@ekaty.com" \
  -v $(pwd)/data:/app/data \
  -v $(pwd)/logs:/app/logs \
  ekaty-agent:latest
```

## Scheduled Sync with Docker

### Option 1: Built-in Cron (Already configured in Dockerfile)

The container runs cron automatically with daily sync at 3:00 AM.

```bash
# Check cron logs
docker exec ekaty-agent cat /app/logs/cron.log
```

### Option 2: External Cron (Host machine)

```bash
# Add to host crontab
0 3 * * * docker exec ekaty-agent php bin/agent sync >> /var/log/ekaty-sync.log 2>&1
```

### Option 3: Docker Compose with Separate Sync Service

```yaml
version: '3.8'
services:
  ekaty-agent-sync:
    build: .
    command: sh -c "while true; do php bin/agent sync && sleep 86400; done"
    env_file:
      - .env
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
```

## Monitoring & Health Checks

### Docker Health Check

```bash
# Check container health
docker inspect --format='{{.State.Health.Status}}' ekaty-agent

# View health check logs
docker inspect --format='{{range .State.Health.Log}}{{.Output}}{{end}}' ekaty-agent
```

### Manual Health Check

```bash
docker exec ekaty-agent php bin/agent health
```

### Automated Monitoring with Healthchecks.io

```bash
# Add to .env
ALERT_WEBHOOK_URL=https://hc-ping.com/your-check-uuid

# The agent will ping this URL after each successful sync
```

## Data Persistence

### Volumes

```bash
# Data is persisted in mounted volumes
./data  -> /app/data   (SQLite database)
./logs  -> /app/logs   (Log files)

# Backup database
cp data/ekaty.db data/ekaty.db.backup

# Restore database
cp data/ekaty.db.backup data/ekaty.db
```

### PostgreSQL (Alternative)

```yaml
version: '3.8'
services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: ekaty
      POSTGRES_USER: ekaty
      POSTGRES_PASSWORD: secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data

  ekaty-agent:
    build: .
    depends_on:
      - postgres
    environment:
      - DB_TYPE=pgsql
      - DB_HOST=postgres
      - DB_NAME=ekaty
      - DB_USER=ekaty
      - DB_PASS=secure_password

volumes:
  postgres_data:
```

## Security Best Practices

### 1. Never Commit Secrets

```bash
# .gitignore already includes:
.env
.env.local
*.key
*.pem
```

### 2. Use Read-Only Filesystem

```bash
docker run -d \
  --name ekaty-agent \
  --read-only \
  --tmpfs /tmp \
  -v $(pwd)/data:/app/data \
  -v $(pwd)/logs:/app/logs \
  ekaty-agent:latest
```

### 3. Run as Non-Root User

```dockerfile
# Already included in Dockerfile
USER www-data
```

### 4. Scan for Vulnerabilities

```bash
# Scan image
docker scan ekaty-agent:latest

# Use Trivy
trivy image ekaty-agent:latest
```

## Troubleshooting

### Container won't start

```bash
# Check logs
docker logs ekaty-agent

# Interactive debugging
docker run -it --rm ekaty-agent:latest sh
```

### Permission issues

```bash
# Fix data directory permissions
sudo chown -R 1000:1000 data logs

# Or run with current user
docker run -d \
  --user $(id -u):$(id -g) \
  ...
```

### Database locked

```bash
# Check if container is running
docker ps

# Stop container
docker stop ekaty-agent

# Remove lock file
rm data/ekaty.db-journal

# Restart
docker start ekaty-agent
```

## Production Deployment

### AWS ECS

```bash
# Push image to ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin your-account.dkr.ecr.us-east-1.amazonaws.com
docker tag ekaty-agent:latest your-account.dkr.ecr.us-east-1.amazonaws.com/ekaty-agent:latest
docker push your-account.dkr.ecr.us-east-1.amazonaws.com/ekaty-agent:latest

# Store secrets in AWS Secrets Manager
aws secretsmanager create-secret --name ekaty/google-api-key --secret-string "your_key"

# Reference in ECS task definition
{
  "secrets": [
    {
      "name": "GOOGLE_PLACES_API_KEY",
      "valueFrom": "arn:aws:secretsmanager:region:account:secret:ekaty/google-api-key"
    }
  ]
}
```

### Kubernetes

```bash
# Create secret
kubectl create secret generic ekaty-secrets \
  --from-literal=google-api-key=your_key

# Deploy
kubectl apply -f k8s/deployment.yaml

# Use secret in pod
env:
  - name: GOOGLE_PLACES_API_KEY
    valueFrom:
      secretKeyRef:
        name: ekaty-secrets
        key: google-api-key
```

## Updates and Maintenance

```bash
# Pull latest code
git pull origin main

# Rebuild container
docker-compose build --no-cache

# Restart with new image
docker-compose up -d

# Or use rolling update
docker-compose up -d --force-recreate
```
