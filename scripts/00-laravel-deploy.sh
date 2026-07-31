#!/usr/bin/env sh
# Run automatically on every container start (RUN_SCRIPTS=1 in the
# Dockerfile makes the base image execute everything in this folder).
php artisan migrate --force
