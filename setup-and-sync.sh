#!/bin/bash

# eKaty Restaurant Database - Setup and Sync Script
# This script helps set up the environment and run the sync

set -e

echo "======================================"
echo "eKaty Restaurant Database Agent"
echo "Setup and Sync Script"
echo "======================================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Check for API key
if grep -q "your_api_key_here" .env; then
    echo ""
    echo "⚠️  WARNING: Google Places API Key not configured!"
    echo ""
    echo "Please update the .env file with your Google Places API key:"
    echo "  1. Edit .env file"
    echo "  2. Replace 'your_api_key_here' with your actual API key"
    echo "  3. Get an API key from: https://console.cloud.google.com/"
    echo ""
    read -p "Do you want to enter your API key now? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        read -p "Enter your Google Places API Key: " api_key
        sed -i "s/your_api_key_here/$api_key/" .env
        echo "✓ API key configured"
    else
        echo "Skipping sync - API key required"
        exit 1
    fi
fi

# Check if composer dependencies are installed
if [ ! -d vendor ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Create necessary directories
mkdir -p data logs

# Run health check
echo ""
echo "Running health check..."
php bin/agent health || true

# Run sync
echo ""
echo "Running restaurant data sync..."
php bin/agent sync

echo ""
echo "✓ Sync completed!"
echo ""
echo "To view statistics, run:"
echo "  php bin/agent stats"
echo ""
echo "To set up automated syncing with cron, add this to your crontab:"
echo "  0 3 * * * cd $(pwd) && php bin/agent sync >> logs/cron.log 2>&1"
echo ""
