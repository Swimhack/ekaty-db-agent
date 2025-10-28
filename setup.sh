#!/bin/bash

# eKaty Agent Setup Script for Linux/Mac
# Run with: bash setup.sh

echo "====================================="
echo "  eKaty Restaurant Agent Setup"
echo "====================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Check PHP
echo -e "${YELLOW}Checking PHP...${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo -e "${GREEN}✓ PHP $PHP_VERSION found${NC}"
else
    echo -e "${RED}✗ PHP not found. Please install PHP 8.1+ first.${NC}"
    exit 1
fi

# Check Composer
echo -e "${YELLOW}Checking Composer...${NC}"
if command -v composer &> /dev/null; then
    echo -e "${GREEN}✓ Composer found${NC}"
else
    echo -e "${RED}✗ Composer not found. Please install Composer first.${NC}"
    exit 1
fi

# Install dependencies
echo ""
echo -e "${YELLOW}Installing dependencies...${NC}"
composer install

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${RED}✗ Failed to install dependencies${NC}"
    exit 1
fi

# Create .env file
echo ""
echo -e "${YELLOW}Setting up environment...${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✓ Created .env file${NC}"
    echo ""
    echo -e "${YELLOW}⚠ IMPORTANT: Edit .env and add your Google Places API key!${NC}"
    echo -e "${YELLOW}  Open .env in a text editor and set:${NC}"
    echo -e "${YELLOW}  GOOGLE_PLACES_API_KEY=your_api_key_here${NC}"
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

# Create directories
echo ""
echo -e "${YELLOW}Creating directories...${NC}"
for dir in data logs; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        echo -e "${GREEN}✓ Created $dir/${NC}"
    else
        echo -e "${GREEN}✓ $dir/ already exists${NC}"
    fi
done

# Make CLI executable
chmod +x bin/agent
echo -e "${GREEN}✓ Made bin/agent executable${NC}"

# Test health
echo ""
echo -e "${YELLOW}Running health check...${NC}"
php bin/agent health

# Summary
echo ""
echo "====================================="
echo "  Setup Complete!"
echo "====================================="
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Edit .env and add your Google Places API key"
echo "2. Run: php bin/agent health"
echo "3. Run: php bin/agent sync"
echo ""
echo "For help: php bin/agent list"
echo ""
