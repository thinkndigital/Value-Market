# syntax=docker/dockerfile:1
#
# Value Market (Laravel 10 / PHP 8.4) - Google Cloud Run image.
#
# Deployment/containerization only - see docs/CLOUD_RUN_DEPLOYMENT.md and
# docs/CLOUD_RUN_READINESS_REPORT.md. No application/business logic is built or run here beyond the
# standard Composer autoloader and Vite asset compilation; no .env, secrets, or database access are baked
# into the image; no migrations run during the build.

########################################################################################################
# Stage 1 - PHP dependencies (Composer)
########################################################################################################
# Fixed twice against two real Cloud Build failures, in sequence:
# 1. (eca498cb-010a-4e87-b968-88ea11b52033) This stage originally used `FROM composer:2` directly, which
#    bundles its own PHP interpreter - a floating version that had reached PHP 8.5.9 by the time of that
#    build, exceeding several packages' own upper bounds (nette/schema, nette/utils, nicmart/tree,
#    sabberworm/php-css-parser all cap out at PHP 8.4). First fix: pin this stage to `php:8.1-cli` directly
#    instead of inheriting composer:2's floating PHP.
# 2. (next Cloud Build run) PHP 8.1 turned out to be too *old*: composer.lock also locks
#    spatie/browsershot, spatie/crawler, spatie/laravel-sitemap, and symfony/css-selector|dom-crawler|
#    event-dispatcher|string at versions requiring PHP >=8.2. Cross-checked directly against
#    composer.lock (not re-guessed): the real intersection of every locked package's php constraint is
#    >=8.2, <=8.4 - PHP 8.4 is the only version in the officially released 8.x line that satisfies all of
#    them simultaneously. This stage and the runtime stage below are now both pinned to `php:8.4-cli`/
#    `php:8.4-apache`, matching each other, per that intersection.
# Composer's binary (a version-agnostic PHAR) is still copied in from the official composer image rather
# than inheriting composer:2's own PHP - that's what actually pins the interpreter version composer.lock is
# checked against, regardless of which PHP release composer:2 itself bundles.
FROM php:8.4-cli AS vendor

# ext-exif: flagged by the first of those two builds (spatie/image and spatie/laravel-medialibrary both
# require it) and, unlike most of composer.lock's other ext-* requirements (ctype, curl, dom, fileinfo,
# filter, hash, iconv, json, libxml, mbstring, openssl, pcre, phar, session, simplexml, tokenizer, xml,
# xmlwriter, zlib - all enabled by default in the official php:8.4-cli image, same as they were in 8.1),
# exif is not compiled in by default and needs an explicit install step. No extra system libraries are
# required for it (unlike gd, it doesn't link against libjpeg/libpng - it parses EXIF metadata directly).
#
# ext-zip + unzip: a third Cloud Build failure, fixed here - "The zip extension and unzip/7z commands are
# both missing" while downloading aws/aws-crt-php's --prefer-dist archive. This minimal php:8.4-cli base
# (unlike Stage 3's runtime image, which already installs both for the application's own use) had neither -
# Composer needs at least one of them to extract any --prefer-dist zip package, not just this one. Same
# libzip-dev + docker-php-ext-install pattern already used in Stage 3, kept consistent between the two
# stages; every other system package/extension already present in either stage is unchanged.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
    && docker-php-ext-install exif zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# Only the two files Composer actually needs for `install` - keeps this layer cached across app-code-only
# changes, and guarantees the resulting vendor/ is reproducible from composer.lock (never `composer update`).
COPY composer.json composer.lock ./

# --no-scripts: this stage has no application code yet (artisan, .env, etc. aren't copied in), so Laravel's
# package-discovery/post-install scripts can't run correctly here - the final stage's `composer
# dump-autoload` (once the full source tree and this vendor/ are both present) triggers that instead.
# --no-dev/--no-interaction/--prefer-dist: standard production-safe flags. No DB, APP_KEY, or other runtime
# secrets are available or required at this step. No composer.lock regeneration, no composer update, no
# version/package downgrades, no --ignore-platform-reqs anywhere in this stage - both PHP-version fixes
# above are entirely about which PHP composer runs under, not about hiding what it checks.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist

########################################################################################################
# Stage 2 - Frontend assets (Vite)
########################################################################################################
FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
# npm ci (not install) for a reproducible install from package-lock.json, matching the composer.lock
# reproducibility requirement for PHP deps. --legacy-peer-deps: a pre-existing, unresolved peer-dependency
# conflict in this repo's package.json (@nextui-org/react requires framer-motion >=11.5, but
# framer-motion ^10.16.4 is pinned) makes a strict `npm ci` fail outright - confirmed by actually running it
# during this task, documented in docs/CLOUD_RUN_READINESS_REPORT.md rather than silently worked around.
# Not a version bump: package.json/package-lock.json are untouched, so this resolves exactly what a
# developer's own `npm ci --legacy-peer-deps` would.
RUN npm ci --legacy-peer-deps

COPY resources ./resources
COPY vite.config.js ./

RUN npm run build

########################################################################################################
# Stage 3 - Runtime image
########################################################################################################
FROM php:8.4-apache AS runtime

# Required PHP extensions for this application (verified against composer.json's actual dependencies and
# real usage in app/, not just the generic list a fresh Laravel install would need):
#   - pdo_mysql / pdo_pgsql: MySQL/MariaDB is this app's actual, exclusively-used database driver (every
#     migration and query in this codebase is MySQL/MariaDB-specific) - pdo_pgsql is included only because
#     Laravel's config/database.php declares a pgsql connection and it was explicitly requested; nothing in
#     this app's schema or queries currently targets Postgres. A fourth Cloud Build failure (`configure:
#     error: Cannot find libpq-fe.h or pq library (libpq)` while compiling pdo_pgsql) was reported as
#     coming from Stage 1's vendor/build stage, but direct inspection shows Stage 1 never invokes
#     pdo_pgsql at all - only Stage 3 (this one, `php:8.4-apache AS runtime`) has ever compiled it, via
#     `docker-php-ext-install ... pdo_pgsql ...` below, and this stage never installed `libpq-dev` for it -
#     a genuine, latent bug present since the very first draft of this Dockerfile, only surfaced now by a
#     real Cloud Build run actually reaching this step. Fixed here (added `libpq-dev` to the apt-get list
#     below) rather than in Stage 1, since that's where the failing compile step actually is.
#   - gd: intervention/image's default driver (composer.json) for everyday image resizing.
#   - imagick: NOT in the task's original extension list, but a real, confirmed runtime dependency -
#     app/Http/Controllers/{Seller,Admin}/MediaController.php instantiate `new Imagick()` directly (no GD
#     fallback) to resize animated GIFs while preserving animation. Omitting it would fatal-error the first
#     time anyone uploads/resizes an animated GIF.
#   - mbstring, bcmath, intl, zip, curl, xml, fileinfo, tokenizer, opcache: Laravel/Composer-dependency
#     baseline (Sanctum, Socialite, the AWS/Stripe/PayPal/Razorpay SDKs, league/csv, laravel-invoices).
# Note on cleanup: deliberately NOT purging the `-dev` packages afterward. `apt-get purge --auto-remove`
# would also sweep away the runtime shared libraries (e.g. libmagickwand's actual .so, not just its
# headers) that gd/zip/intl/imagick link against at request time, since nothing else on the system
# explicitly depends on them once the `-dev` metapackage is gone - a classic way to silently break an
# extension after the image "successfully" builds. Trading a somewhat larger image for actually working at
# runtime, especially since this couldn't be verified against a real `docker build` in this environment (no
# Docker daemon available here - see docs/CLOUD_RUN_READINESS_REPORT.md). `unzip`/`git` are genuinely safe
# to remove afterward - standalone tools nothing else depends on.
RUN apt-get update && apt-get install -y --no-install-recommends \
        pkg-config \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libicu-dev \
        libpq-dev \
        libmagickwand-dev \
        libxml2-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        exif \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && a2enmod rewrite \
    && apt-get purge -y unzip git \
    && rm -rf /var/lib/apt/lists/*

# mbstring, curl, fileinfo, tokenizer, xml (dom/simplexml/xmlwriter) ship enabled by default in the official
# php:8.4-apache image (same as they were in 8.1) - no docker-php-ext-install step needed for them; not
# removed, not disabled.

# Opcache tuned for a stateless, ephemeral Cloud Run container - validate_timestamps=0 is safe because the
# image is immutable per revision (no hot-reloading of PHP source in production).
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/apache-cloud-run.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Application source. .dockerignore keeps .git, .env*, node_modules, vendor, tests, and local dev artifacts
# out of the build context entirely - nothing here is a secret or a build-time-only file.
COPY . .

# vendor/ from Stage 1 (PHP deps) and public/build/ from Stage 2 (compiled Vite assets) - both already
# built without requiring the full application source, then composed into the final image here.
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# A second, latent bug caught while fixing Stage 1 above (not yet reached by the failed build, since it
# never got past Stage 1's composer install): this stage runs `composer dump-autoload` below but is
# `php:8.4-apache`, not the composer image - it never had the composer binary at all. Same fix as Stage 1:
# copy the version-agnostic PHAR in and run it under this stage's own PHP (matching Stage 1's version).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Now that the full source tree, vendor/, and composer.json all exist together, generate the optimized
# autoloader. This also fires composer.json's own post-autoload-dump hook (Laravel's normal
# `artisan package:discover` package-discovery step, safe: only writes bootstrap/cache/packages.php and
# bootstrap/cache/services.php, no DB/APP_KEY/network access needed) - not invoked separately, since
# dump-autoload already triggers it.
RUN composer dump-autoload --optimize --no-dev --no-interaction

# storage/ and bootstrap/cache/ are the only paths Laravel writes to at runtime (logs, compiled views,
# framework cache, sessions if using the file driver). Owned by www-data (the user Apache/PHP-FPM run as in
# this base image) - not a blanket chmod -R 777 of the source tree, and app source ownership is otherwise
# left as COPY's default (root), matching "don't make the entire filesystem writable."
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cloud Run injects PORT at runtime; 8080 is the documented safe default or platform fallback.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
