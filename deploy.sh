#!/bin/bash
set -e

echo "Starting Zero-Downtime Deployment..."

TIMESTAMP=$(date +%Y%m%d%H%M%S)
RELEASE_DIR="releases/${TIMESTAMP}"

mkdir -p ${RELEASE_DIR}
cp -r . ${RELEASE_DIR}/

cd ${RELEASE_DIR}
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

cd ../..
ln -sfn ${RELEASE_DIR} current

echo "Deployment completed successfully! Symlink points to ${RELEASE_DIR}"
