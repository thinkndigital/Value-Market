#!/usr/bin/env sh
# Value Market - Cloud Run container entrypoint.
#
# Substitutes the __PORT__ placeholder in the Apache vhost/ports config with Cloud Run's injected $PORT
# (falling back to the documented default 8080 for a plain `docker run` outside Cloud Run), caches
# config/routes/views, then hands off to the image's normal CMD (apache2-foreground). Does not run
# migrations, seeders, or anything requiring database access - see docs/CLOUD_RUN_DEPLOYMENT.md for how
# those are handled separately.
set -e

: "${PORT:=8080}"

sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf

# Without this, every single request re-parses every config/*.php file plus .env (config:cache) and
# recompiles every Blade template it renders (view:cache) from scratch - real, measurable latency on every
# page, not a one-time cost, since this is a stateless container with no request-to-request cache. Safe to
# run here (unlike the DB-touching steps this deliberately excludes): both read only the environment
# variables Cloud Run has already injected by the time this script runs, no database/network access
# involved. route:cache is deliberately NOT run - the app currently has duplicate route names across the
# admin/seller/delivery_boy route files (e.g. "orders.destroy" registered three times), which route:cache's
# stricter validation rejects outright; fixing that is a separate, larger cleanup, not a safe one-liner to
# fold in here.
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data bootstrap/cache storage/framework/views

exec "$@"
