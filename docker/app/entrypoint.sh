#!/bin/sh

set -eu

mkdir -p /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage/logs

exec "$@"
