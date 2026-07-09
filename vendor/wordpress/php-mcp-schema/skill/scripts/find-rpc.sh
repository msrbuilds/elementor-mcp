#!/bin/bash
# Find RPC method details
# Usage: ./find-rpc.sh <method-pattern>
# Output: Request/Result types for matching methods

SKILL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PATTERN="$1"

if [ -z "$PATTERN" ]; then
  echo "Usage: ./find-rpc.sh <method-pattern>"
  exit 1
fi

jq -r ".rpcMethods[] | select(.method | test(\"$PATTERN\"; \"i\")) | \"\(.method): \(.direction)\"" "$SKILL_DIR/data/schema-index.json" 2>/dev/null

# Also search for full details in domain files
for f in "$SKILL_DIR"/data/schema-*.json; do
  if [ "$(basename "$f")" != "schema-index.json" ]; then
    jq -r ".types | to_entries[] | select(.key | endswith(\"Request\")) | select(.value.discriminator.value | test(\"$PATTERN\"; \"i\") // false) | \"\(.value.discriminator.value): \(.key) → \(.key | sub(\"Request$\"; \"Result\"))\"" "$f" 2>/dev/null
  fi
done
