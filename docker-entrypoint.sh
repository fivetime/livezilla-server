#!/bin/sh
set -e

# Security: once LiveZilla has been installed (config.php exists on the
# persistent _config volume), the install/ folder is no longer needed and
# should be removed. We do it here on every startup so the deletion survives
# container recreation / image rebuilds — without requiring the user to click
# the "REMOVE" button in the web UI each time.
if [ -f /var/www/html/_config/config.php ] && [ -d /var/www/html/install ]; then
    echo "[entrypoint] LiveZilla already installed -> removing install/ folder"
    rm -rf /var/www/html/install
fi

exec "$@"
