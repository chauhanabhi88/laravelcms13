#!/usr/bin/env bash
#
# Production / staging deploy (plan M21 §9). Run from the release directory as the web user,
# with .env already in place. On Forge, Envoyer or Laravel Cloud, paste the steps into the deploy
# hook instead. Zero-downtime setups run this in the new release before switching the symlink.
#
#   ./deploy.sh            full deploy
#   SKIP_BUILD=1 ./deploy.sh   when assets are built in CI and shipped with the release
#
set -euo pipefail

cd "$(dirname "$0")"

echo "==> Maintenance mode (503 with Retry-After)"
php artisan down --retry=60

echo "==> PHP dependencies (PHP 8.4)"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Database migrations (module order follows module.json priorities)"
php artisan migrate --force

echo "==> Module assets -> public/modules"
php artisan module:publish --all --no-interaction

if [ "${SKIP_BUILD:-0}" != "1" ]; then
    echo "==> Front-end build"
    npm ci
    npm run build
fi
rm -f public/hot

echo "==> Storage link, Passport keys (first deploy only)"
[ -e public/storage ] || php artisan storage:link
[ -f storage/oauth-private.key ] || php artisan passport:keys

echo "==> Caches (config, routes, views, events)"
php artisan optimize

echo "==> Sitemaps"
php artisan storefront:sitemap

echo "==> Restart queue workers (Supervisor starts them again)"
php artisan queue:restart

php artisan up

echo "==> Go-live preflight"
php artisan core:launch-check
