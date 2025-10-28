FROM php:8.2-cli

LABEL maintainer="james@ekaty.com"
LABEL description="eKaty Restaurant Database Maintenance Agent"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    cron \
    && docker-php-ext-install zip pdo pdo_sqlite pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
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

# Default command
CMD ["php", "bin/agent", "sync"]
