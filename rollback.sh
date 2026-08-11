#!/bin/bash
set -e

echo "Starting Deployment Rollback..."

PREVIOUS_RELEASE=$(ls -td releases/* 2>/dev/null | sed -n '2p')

if [ -z "$PREVIOUS_RELEASE" ]; then
    echo "No previous release folder found to rollback!"
    exit 1
fi

ln -sfn ${PREVIOUS_RELEASE} current

cd current
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

echo "Rollback completed successfully! Symlink reverted to ${PREVIOUS_RELEASE}"
