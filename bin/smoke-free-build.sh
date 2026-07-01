#!/usr/bin/env bash
# Simulate the FREE build: strip Pro-manifest paths from a copy, then lint every
# remaining PHP file. NOTE: php -l is SYNTAX-ONLY — it cannot catch references to
# missing classes (that is runtime). The real acceptance is the WP activation
# test documented in the plan (Task 5 Step 3), not this script.
set -euo pipefail
PLUGIN_DIR="f:/laragon/www/msrplugins/wp-content/plugins/elementor-mcp"
PHP="/f/laragon/bin/php/php-8.4.15-nts-Win32-vs17-x64/php.exe"
TMP="$(mktemp -d)"
cp -r "$PLUGIN_DIR/." "$TMP/"
while read -r p; do
	[ -z "$p" ] && continue
	case "$p" in \#*) continue;; esac
	rm -rf "${TMP:?}/$p"
done < "$PLUGIN_DIR/pro-manifest.txt"
find "$TMP" -name '*.php' -not -path '*/vendors/*' -not -path '*/pro/*' -print0 \
	| xargs -0 -n1 "$PHP" -l >/dev/null
echo "OK: free tree lints clean with Pro paths removed"
rm -rf "$TMP"
