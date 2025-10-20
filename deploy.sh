#!/bin/bash

# OnlyFarms Backend Deployment Script
# Run this script on your production server

echo "🚀 Starting OnlyFarms Backend Deployment..."

# Step 1: Install/Update Dependencies
echo "📦 Installing dependencies..."
composer install --optimize-autoloader --no-dev

# Step 2: Generate Application Key
echo "🔑 Generating application key..."
php artisan key:generate

# Step 3: Clear and Cache Configurations
echo "⚡ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 4: Run Database Migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Step 5: Create Storage Link
echo "📁 Creating storage link..."
php artisan storage:link

# Step 6: Set Permissions
echo "🔒 Setting file permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

# Step 7: Clear Application Cache
echo "🧹 Clearing application cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Step 8: Re-cache for production
echo "⚡ Re-caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Deployment completed successfully!"
echo "🌐 Your OnlyFarms backend is now live!"
echo "🔗 Test your API at: https://yourdomain.com/api/products"
