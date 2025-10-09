#!/usr/bin/env bash
set -euo pipefail
echo "Stopping containers and removing volumes and data..."
docker compose down -v --remove-orphans || true
rm -rf wp-content/plugins/inat-observations-wp
rm -rf wp-content/uploads
echo "Clean complete. You can start fresh with ./run.sh"
