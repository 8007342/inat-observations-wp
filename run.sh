#!/usr/bin/env bash
set -euo pipefail
echo "Starting or restarting dev environment..."
docker compose down || true
docker compose pull
docker compose up -d --build
echo "WordPress should be available at http://localhost:8080"
