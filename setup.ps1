# eKaty Agent Setup Script for Windows
# Run with: powershell -ExecutionPolicy Bypass -File setup.ps1

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  eKaty Restaurant Agent Setup" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Check PHP
Write-Host "Checking PHP..." -ForegroundColor Yellow
try {
    $phpVersion = php -r "echo PHP_VERSION;"
    Write-Host "✓ PHP $phpVersion found" -ForegroundColor Green
} catch {
    Write-Host "✗ PHP not found. Please install PHP 8.1+ first." -ForegroundColor Red
    exit 1
}

# Check Composer
Write-Host "Checking Composer..." -ForegroundColor Yellow
try {
    $composerVersion = composer --version
    Write-Host "✓ Composer found" -ForegroundColor Green
} catch {
    Write-Host "✗ Composer not found. Please install Composer first." -ForegroundColor Red
    exit 1
}

# Install dependencies
Write-Host ""
Write-Host "Installing dependencies..." -ForegroundColor Yellow
composer install

if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Failed to install dependencies" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Dependencies installed" -ForegroundColor Green

# Create .env file
Write-Host ""
Write-Host "Setting up environment..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "✓ Created .env file" -ForegroundColor Green
    Write-Host ""
    Write-Host "⚠ IMPORTANT: Edit .env and add your Google Places API key!" -ForegroundColor Yellow
    Write-Host "  Open .env in a text editor and set:" -ForegroundColor Yellow
    Write-Host "  GOOGLE_PLACES_API_KEY=your_api_key_here" -ForegroundColor Yellow
} else {
    Write-Host "✓ .env file already exists" -ForegroundColor Green
}

# Create directories
Write-Host ""
Write-Host "Creating directories..." -ForegroundColor Yellow
$dirs = @("data", "logs")
foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) {
        New-Item -ItemType Directory -Path $dir | Out-Null
        Write-Host "✓ Created $dir/" -ForegroundColor Green
    } else {
        Write-Host "✓ $dir/ already exists" -ForegroundColor Green
    }
}

# Test health
Write-Host ""
Write-Host "Running health check..." -ForegroundColor Yellow
php bin/agent health

# Summary
Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  Setup Complete!" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Edit .env and add your Google Places API key" -ForegroundColor White
Write-Host "2. Run: php bin/agent health" -ForegroundColor White
Write-Host "3. Run: php bin/agent sync" -ForegroundColor White
Write-Host ""
Write-Host "For help: php bin/agent list" -ForegroundColor White
Write-Host ""
