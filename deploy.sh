#!/bin/bash
##############################################################
#  cPanel Deployment Script
#  Run this on your cPanel server via SSH AFTER uploading files
##############################################################

set -e  # Stop on any error

echo ""
echo "======================================================"
echo "  E-Commerce Marketplace - cPanel Deployment"
echo "======================================================"
echo ""

# --- Step 1: Check .env exists ---
if [ ! -f ".env" ]; then
    echo "[ERROR] .env file not found!"
    echo "Copy .env.production.example to .env and fill in your values."
    exit 1
fi

echo "[1/6] Clearing old caches..."
php artisan optimize:clear

echo "[2/6] Installing/updating PHP dependencies (no dev)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/6] Running database migrations..."
php artisan migrate --force

echo "[4/6] Caching config, routes, and views for speed..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[5/6] Creating storage symlink (if not exists)..."
php artisan storage:link --quiet 2>/dev/null || true

echo "[6/6] Setting correct file permissions..."
find storage -type d -exec chmod 755 {} \;
find storage -type f -exec chmod 644 {} \;
find bootstrap/cache -type d -exec chmod 755 {} \;

echo ""
echo "======================================================"
echo "  Deployment Complete!"
echo "======================================================"
echo ""
echo "  Your site should be live now."
echo "  If you have a queue worker (for dropshipping sync),"
echo "  restart it: php artisan queue:restart"
echo ""

