#!/bin/bash
# Search for types by name or property
# Usage: ./search-types.sh <pattern> [domain]
# Output: Matching type names and locations

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PATTERN="$1"
DOMAIN="${2:-all}"

if [ -z "$PATTERN" ]; then
  echo "Usage: ./search-types.sh <pattern> [domain]"
  echo "  domain: all, common, server, client"
  exit 1
fi

search_file() {
  local file="$1"
  if [ -f "$file" ]; then
    jq -r ".types | to_entries[] | select(.key | test(\"$PATTERN\"; \"i\")) | \"\(.key) (\(.value.domain)/\(.value.subdomain))\"" "$file" 2>/dev/null
  fi
}

if [ "$DOMAIN" = "all" ]; then
  for f in "$SKILL_DIR"/data/schema-*.json; do
    if [ "$(basename "$f")" != "schema-index.json" ]; then
      search_file "$f"
    fi
  done
else
  search_file "$SKILL_DIR/data/schema-$DOMAIN.json"
fi
