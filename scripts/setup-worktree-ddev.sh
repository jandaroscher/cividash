#!/usr/bin/env bash
# Setup DDEV for a git worktree with a unique project name.
#
# Usage:
#   cd /path/to/worktree
#   bash scripts/setup-worktree-ddev.sh [suffix]
#
# If no suffix is given, it extracts it from the branch name.
# Example: branch "fix/123-time-periods" -> suffix "123-time-periods"
#
# This creates a separate DDEV instance with its own database,
# avoiding conflicts with the main project's DDEV.

set -euo pipefail

MAIN_PROJECT_NAME="open-source-dashboard"

# Determine suffix
if [[ -n "${1:-}" ]]; then
    SUFFIX="$1"
else
    BRANCH=$(git branch --show-current 2>/dev/null || echo "")
    if [[ -z "$BRANCH" ]]; then
        echo "Error: Not in a git repo or no branch checked out. Pass a suffix manually."
        exit 1
    fi
    # Last path segment of the branch, lowercased and sanitized
    SUFFIX=$(echo "$BRANCH" | sed 's|.*/||' | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g' | cut -c1-20)
fi

NEW_NAME="${MAIN_PROJECT_NAME}-${SUFFIX}"

echo "Setting up DDEV worktree: ${NEW_NAME}"
echo "  Main project:  ${MAIN_PROJECT_NAME}"
echo "  Worktree name: ${NEW_NAME}"
echo ""

# Check if .ddev/config.yaml exists
if [[ ! -f .ddev/config.yaml ]]; then
    echo "Error: .ddev/config.yaml not found. Are you in the project root?"
    exit 1
fi

# Rename the project and its additional_hostnames in one pass; a second pass
# would match the new name again and append the suffix twice.
sed -i.bak "s/${MAIN_PROJECT_NAME}/${NEW_NAME}/g" .ddev/config.yaml

# Clean up backup
rm -f .ddev/config.yaml.bak

echo "Updated .ddev/config.yaml -> name: ${NEW_NAME}"

# Copy .env if missing
if [[ ! -f .env ]]; then
    if [[ -f .env.example ]]; then
        cp .env.example .env
        echo "Copied .env.example -> .env"
    fi
fi

# Start DDEV
echo ""
echo "Starting DDEV..."
ddev start

# Generate app key if empty
APP_KEY=$(grep "^APP_KEY=" .env | cut -d= -f2)
if [[ -z "$APP_KEY" ]]; then
    ddev exec php artisan key:generate
    echo "Generated APP_KEY"
fi

# Install dependencies
echo ""
echo "Installing dependencies..."
ddev exec composer install --no-interaction
ddev exec npm install

# Run migrations and seed
echo ""
echo "Setting up database..."
ddev exec php artisan migrate:fresh --seed
ddev exec php artisan tenancy:backfill

echo ""
echo "============================================"
echo "DDEV worktree ready: ${NEW_NAME}"
echo "URL: https://${NEW_NAME}.ddev.site"
echo "Admin: https://${NEW_NAME}.ddev.site/admin"
echo "============================================"
