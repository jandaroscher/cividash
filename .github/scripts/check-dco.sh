#!/usr/bin/env bash
# Checks that every commit in the given range carries a Developer Certificate
# of Origin sign-off by a person. Commits by bots are exempt; a sign-off by an
# AI assistant or one of our agents does not count. Usage: check-dco.sh <rev-range>
set -euo pipefail
range="$1"
# Separate assignment so an invalid range fails instead of checking nothing.
shas=$(git rev-list --no-merges "$range")
failed=0
for sha in $shas; do
  author_email=$(git show -s --format='%ae' "$sha")
  case "$author_email" in
    *'[bot]@users.noreply.github.com') continue ;;
  esac
  # A valid sign-off is "Name <email>"; folded trailers are unfolded first.
  signoffs=$(git show -s --format='%(trailers:key=Signed-off-by,valueonly,unfold)' "$sha" \
    | grep -E '^[^<]*[^<[:space:]][^<]*<[^>@]+@[^>]+>' \
    | grep -v -i -e 'noreply@anthropic.com' -e '<agent+[^>]*@janda-roscher.de>' || true)
  if [ -z "$signoffs" ]; then
    echo "::error::$(git show -s --format='%h %s' "$sha"): no Signed-off-by from a person"
    failed=1
  fi
done
exit "$failed"
