#!/bin/sh
set -e

# Loaded after zz-drupal.ini, so it overrides its xdebug.mode = off. Written to
# an ini file (not XDEBUG_MODE) so `docker compose exec` CLI runs pick it up too.
ini=/usr/local/etc/php/conf.d/zz-xdebug-mode.ini
if [ "$XDEBUG_ENABLE" = "true" ]; then
    echo "xdebug.mode = debug" > "$ini"
else
    rm -f "$ini"
fi

exec docker-php-entrypoint "$@"
