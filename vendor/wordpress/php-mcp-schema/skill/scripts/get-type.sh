#!/bin/bash
# Get full details for a specific type
# Usage: ./get-type.sh <TypeName>
# Output: JSON with type details, relationships, usage

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TYPE_NAME="$1"

if [ -z "$TYPE_NAME" ]; then
  echo "Usage: ./get-type.sh <TypeName>"
  exit 1
fi

for f in "$SKILL_DIR"/data/schema-*.json; do
  if [ "$(basename "$f")" != "schema-index.json" ]; then
    result=$(jq -r ".types.\"$TYPE_NAME\" // empty" "$f" 2>/dev/null)
    if [ -n "$result" ]; then
      echo "$result" | jq .
      exit 0
    fi
  fi
done

echo "Type '$TYPE_NAME' not found"
exit 1
