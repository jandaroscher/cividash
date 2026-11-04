#!/usr/bin/env bash
# Checks that every commit in the given range carries a Developer Certificate
# of Origin sign-off by a person. Commits by bots are exempt; a sign-off by an
# AI assistant does not count. Usage: check-dco.sh <rev-range>
set -euo pipefail

range="$1"
failed=0
for sha in $(git rev-list --no-merges "$range"); do
  author_email=$(git show -s --format='%ae' "$sha")
  case "$author_email" in
    *'[bot]@users.noreply.github.com') continue ;;
  esac
  signoffs=$(git show -s --format='%(trailers:key=Signed-off-by,valueonly)' "$sha" \
    | grep -v -i -e '^$' -e 'noreply@anthropic.com' || true)
  if [ -z "$signoffs" ]; then
    echo "::error::$(git show -s --format='%h %s' "$sha"): no Signed-off-by from a person"
    failed=1
  fi
done
exit "$failed"
