#!/bin/bash

# eKaty Restaurant Database - Cron Setup Script
# This script installs a cron job to automatically sync restaurant data daily

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "======================================"
echo "eKaty Restaurant Database Agent"
echo "Cron Job Setup"
echo "======================================"
echo ""

# Create cron job
CRON_JOB="0 3 * * * cd $SCRIPT_DIR && php bin/agent sync >> logs/cron.log 2>&1"

echo "This will add the following cron job:"
echo "$CRON_JOB"
echo ""
echo "This will run the sync daily at 3:00 AM"
echo ""

read -p "Do you want to install this cron job? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Check if cron job already exists
    if crontab -l 2>/dev/null | grep -q "bin/agent sync"; then
        echo "⚠️  Cron job already exists"
        echo ""
        echo "Current crontab:"
        crontab -l | grep "bin/agent sync"
    else
        # Add cron job
        (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
        echo "✓ Cron job installed successfully"
        echo ""
        echo "To view your crontab:"
        echo "  crontab -l"
        echo ""
        echo "To remove the cron job:"
        echo "  crontab -e"
    fi
else
    echo "Cron job not installed"
    echo ""
    echo "To manually add it later, run:"
    echo "  crontab -e"
    echo ""
    echo "And add this line:"
    echo "$CRON_JOB"
fi

echo ""
echo "To test the sync manually, run:"
echo "  php bin/agent sync"
echo ""
