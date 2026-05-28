#!/bin/bash
set -e

# Align compose project name when running from mounted '/app' folder inside CI container
export COMPOSE_PROJECT_NAME=hrm

echo "=================================================="
echo "⏳ [$(date)] Starting auto-deploy for hrm"
echo "=================================================="

cd "$(dirname "$0")"

# Switch remote to HTTPS and use GITHUB_TOKEN if available
if [ -n "$GITHUB_TOKEN" ]; then
    echo "Using secure runner token for git authentication..."
    git remote set-url origin "https://x-access-token:${GITHUB_TOKEN}@github.com/INNERStudiosPT/hrm.git" || true
else
    git remote set-url origin "https://github.com/INNERStudiosPT/hrm.git" || true
fi

git fetch origin main
git reset --hard origin/main
git clean -fd
git remote set-url origin "https://github.com/INNERStudiosPT/hrm.git" || true

echo "🏗️ Step 2: Rebuilding and restarting hrm-web container..."
# Build the image using the new multi-stage Dockerfile
docker compose build hrm-web
docker compose up -d hrm-web

echo "=================================================="
echo "✅ [$(date)] Deployment finished successfully!"
echo "=================================================="
