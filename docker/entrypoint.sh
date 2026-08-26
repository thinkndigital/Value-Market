#!/usr/bin/env sh
# Value Market - Cloud Run container entrypoint.
#
# Substitutes the __PORT__ placeholder in the Apache vhost/ports config with Cloud Run's injected $PORT
# (falling back to the documented default 8080 for a plain `docker run` outside Cloud Run), then hands off
# to the image's normal CMD (apache2-foreground). Does not run migrations, seeders, or anything requiring
# database/APP_KEY/network access - see docs/CLOUD_RUN_DEPLOYMENT.md for how those are handled separately.
set -e

: "${PORT:=8080}"

sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/^Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf

exec "$@"
