#!/usr/bin/env bash
# Führt Tests der Organisation in der lokalen taiste-Kopie aus. Das Paket liegt dort schon in vendor/, deshalb wird
# vendor/martin3r/platform-organization für die Dauer des Laufs durch einen Symlink auf dieses Repo ersetzt und danach
# exakt wiederhergestellt. Aufruf: tests/run-in-taiste.sh tests/Feature/<Test>.php [weitere …]
set -euo pipefail
MODULE="$(cd "$(dirname "$0")/.." && pwd)"
APP="${TAISTE_PATH:-/Users/martin3r/Platforms/instances/taiste}"
PHP="${PHP_BIN:-/opt/homebrew/opt/php@8.4/bin/php}"
PKG="$APP/vendor/martin3r/platform-organization"
BAK="$APP/vendor/martin3r/.platform-organization.orig.$$"
RUN="org_$$_$RANDOM"
TMP="$(mktemp -d)"
DEST="$APP/tests/Feature/$RUN"
restore() { rm -rf "$DEST" "$TMP"; if [ -d "$BAK" ]; then rm -f "$PKG"; mv "$BAK" "$PKG"; fi; }
trap restore EXIT
mv "$PKG" "$BAK" && ln -s "$MODULE" "$PKG"
mkdir -p "$DEST"
for f in "$@"; do cp "$MODULE/$f" "$DEST/"; done
cd "$APP"
APP_PACKAGES_CACHE="$TMP/packages.php" APP_SERVICES_CACHE="$TMP/services.php" \
DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array \
  "$PHP" -d memory_limit=2G vendor/bin/phpunit "tests/Feature/$RUN" 2>&1 | grep -v "^/Users/.*vendor/" | tail -60
