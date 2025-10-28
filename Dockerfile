FROM php:8.2-cli

LABEL maintainer="james@ekaty.com"
LABEL description="eKaty Restaurant Database Maintenance Agent"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libsqlite3-dev \
    cron \
    && docker-php-ext-install zip pdo pdo_sqlite pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json ./

# Install PHP dependencies (generate composer.lock during build)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Create necessary directories
RUN mkdir -p /app/data /app/logs \
    && chmod -R 755 /app/data /app/logs

# Make CLI executable
RUN chmod +x /app/bin/agent

# Create cron job
RUN echo "0 3 * * * cd /app && php bin/agent sync >> /app/logs/cron.log 2>&1" > /etc/cron.d/ekaty-sync \
    && chmod 0644 /etc/cron.d/ekaty-sync \
    && crontab /etc/cron.d/ekaty-sync

# Health check
HEALTHCHECK --interval=1h --timeout=30s --start-period=5s --retries=3 \
    CMD php bin/agent health || exit 1

# Create startup script
RUN echo '#!/bin/bash\n\nset -e\n\necho "Starting eKaty Agent..."\n\n# Check if API key is set\nif [ -z "$GOOGLE_PLACES_API_KEY" ]; then\n  echo "WARNING: GOOGLE_PLACES_API_KEY not set. Skipping initial sync."\nelse\n  echo "Running initial sync..."\n  php /app/bin/agent sync || echo "Initial sync failed, continuing..."\nfi\n\n# Start cron in foreground\necho "Starting cron daemon..."\ncron -f' > /start.sh \
    && chmod +x /start.sh

# Default command
CMD ["/start.sh"]
