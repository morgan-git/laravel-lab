#!/bin/bash
set -e 

echo "Deploying application..." 

### Enter maintenance mode

(php artisan down) || true 

### Pull the latest version of the code

git pull origin main 

### Install composer dependencies

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader 

### Run database migrations

php artisan migrate --force 

### Clear and optimize caches

php artisan queue:restart
php artisan config:cache
php artisan route:cache
php artisan view:cache 

### Exit maintenance mode

php artisan up 

echo "Application deployed successfully!"
