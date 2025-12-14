.PHONY: help up down restart logs logs-wp logs-db shell-wp shell-db clean fresh install status health backup restore build build-dev

# Default target - show help
help:
	@echo "iNaturalist WordPress Plugin Development Environment"
	@echo "===================================================="
	@echo ""
	@echo "Common commands:"
	@echo "  make up          - Start all containers"
	@echo "  make down        - Stop all containers"
	@echo "  make restart     - Restart all containers"
	@echo "  make logs        - View all logs (follow mode)"
	@echo "  make logs-wp     - View WordPress logs only"
	@echo "  make logs-db     - View MySQL logs only"
	@echo ""
	@echo "Development:"
	@echo "  make shell-wp    - Open bash shell in WordPress container"
	@echo "  make shell-db    - Open MySQL shell"
	@echo "  make status      - Show container status and resource usage"
	@echo "  make health      - Run health checks on services"
	@echo ""
	@echo "Maintenance:"
	@echo "  make clean       - Stop containers and remove volumes (keeps plugin code)"
	@echo "  make fresh       - Complete fresh install (WARNING: destroys all data)"
	@echo "  make install     - Initial setup with environment validation"
	@echo "  make backup      - Backup database to ./backups/"
	@echo "  make restore     - Restore latest database backup"
	@echo ""
	@echo "Optimization:"
	@echo "  make prune       - Clean up Docker system (images, volumes, networks)"
	@echo "  make optimize    - Optimize database and clear caches"
	@echo ""
	@echo "Build & Distribution:"
	@echo "  make build       - Build minified plugin for distribution"
	@echo "  make build-dev   - Build plugin without minification"
	@echo ""

# Start containers
up:
	@echo "Starting WordPress development environment..."
	@./scripts/preflight-check.sh
	docker compose up -d
	@echo ""
	@echo "Environment started successfully!"
	@echo "WordPress: http://localhost:8080"
	@echo "Admin: http://localhost:8080/wp-admin"
	@echo ""
	@echo "Run 'make logs' to view logs or 'make status' for health check"

# Stop containers
down:
	@echo "Stopping containers..."
	docker compose down
	@echo "Containers stopped."

# Restart containers
restart:
	@echo "Restarting containers..."
	docker compose restart
	@echo "Containers restarted."

# View all logs
logs:
	docker compose logs -f

# View WordPress logs
logs-wp:
	docker logs -f wordpress

# View MySQL logs
logs-db:
	docker logs -f mysql

# Shell into WordPress container
shell-wp:
	@echo "Opening bash shell in WordPress container..."
	@echo "Plugin directory: /var/www/html/wp-content/plugins/inat-observations-wp"
	docker exec -it wordpress bash

# MySQL shell
shell-db:
	@echo "Opening MySQL shell (password: wordpress)..."
	docker exec -it mysql mysql -uwordpress -pwordpress wordpress

# Show container status and resource usage
status:
	@echo "Container Status:"
	@echo "================="
	@docker ps --filter "name=wordpress" --filter "name=mysql" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
	@echo ""
	@echo "Resource Usage:"
	@echo "==============="
	@docker stats --no-stream wordpress mysql 2>/dev/null || echo "Containers not running"

# Health check
health:
	@./scripts/health-check.sh

# Clean up (stop and remove volumes, but keep plugin code)
clean:
	@echo "WARNING: This will remove all WordPress data and database!"
	@read -p "Are you sure? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker compose down -v; \
		rm -rf tmp/db tmp/html; \
		echo "Cleanup complete."; \
	else \
		echo "Cancelled."; \
	fi

# Fresh install
fresh: clean
	@echo "Starting fresh install..."
	@./scripts/fresh-install.sh

# Initial install with validation
install:
	@./scripts/fresh-install.sh

# Backup database
backup:
	@./scripts/backup-db.sh

# Restore database
restore:
	@./scripts/restore-db.sh

# Prune Docker system
prune:
	@echo "This will remove unused Docker resources..."
	@read -p "Continue? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker system prune -af --volumes; \
		echo "Docker cleanup complete."; \
	else \
		echo "Cancelled."; \
	fi

# Optimize WordPress
optimize:
	@./scripts/optimize-wordpress.sh

# Build plugin for distribution
build:
	@echo "Building plugin for distribution..."
	@cd wp-content/plugins/inat-observations-wp && php build.php
	@echo "Build complete! Check wp-content/plugins/inat-observations-wp/dist/"

# Build without minification (for debugging)
build-dev:
	@echo "Building plugin (development mode - no minification)..."
	@cd wp-content/plugins/inat-observations-wp && php build.php --no-minify
	@echo "Build complete!"
