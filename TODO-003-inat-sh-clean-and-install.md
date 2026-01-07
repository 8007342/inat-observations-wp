# TODO-003: inat.sh Clean Install Workflow

**Status**: In Progress
**Priority**: Medium
**Reported**: 2026-01-06

## Problem

Development workflow requires manual WordPress installation after each Docker volume wipe:
1. User stops containers
2. User manually deletes Docker volumes
3. User starts containers
4. WordPress shows installation screen
5. User fills out admin username, password, site name
6. User clicks through installation wizard

This is tedious for rapid development iterations. We want fresh WordPress installs without manual intervention.

## Requirements

### Toolbox Integration (Fedora Silverblue)

On Silverblue, Docker is not recommended - use Podman inside toolbox instead.

inat.sh should:
1. Detect if running on Silverblue (check `/etc/os-release`)
2. Check if `inat-observations` toolbox exists
3. Auto-create toolbox if missing
4. Auto-enter toolbox and run commands inside

### --clean Flag

Add `--clean` flag to wipe all WordPress data:

```bash
./inat.sh --clean
```

Should:
1. Stop running containers (podman-compose down)
2. Remove Docker volumes (podman volume rm wordpress_db wordpress_files)
3. Remove any cached state
4. Exit (don't restart containers)

### --clean-and-install Flag

Add `--clean-and-install` flag for full automated reinstall:

```bash
./inat.sh --clean-and-install
```

Should:
1. Run clean steps (stop containers, remove volumes)
2. Start containers with fresh volumes
3. Wait for MySQL to be ready
4. Auto-install WordPress via WP-CLI with:
   - Random site title (e.g., "iNat Dev 2026-01-06 14:32")
   - Random admin user (e.g., "admin_abc123")
   - Random admin password (e.g., "pass_xyz789")
   - Admin email: dev@localhost
   - Language: en_US
   - Skip email verification
5. Activate inat-observations-wp plugin
6. Configure plugin with default settings (sdmyco project)
7. Run initial refresh to fetch observations
8. Print login credentials to console
9. Open browser to http://localhost:8080/wp-admin

### Default Behavior (No Flags)

```bash
./inat.sh
```

Should:
1. Check if toolbox needed (Silverblue detection)
2. Enter toolbox if needed
3. Check if containers running
4. Start containers if stopped
5. Print WordPress URL and instructions
6. Tail logs (optional: -f flag)

## Implementation Plan

### Phase 1: Toolbox Detection (30 min)

```bash
#!/bin/bash
# inat.sh - WordPress development environment for iNat Observations plugin

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLBOX_NAME="inat-observations"

# Detect Fedora Silverblue
is_silverblue() {
    if [[ -f /etc/os-release ]]; then
        grep -q "^VARIANT_ID=silverblue" /etc/os-release
    else
        return 1
    fi
}

# Check if inside toolbox
is_inside_toolbox() {
    [[ -f /run/.containerenv ]] || [[ -f /run/.toolboxenv ]]
}

# Main entry point
main() {
    # If on Silverblue and not inside toolbox, re-exec inside toolbox
    if is_silverblue && ! is_inside_toolbox; then
        echo "Detected Fedora Silverblue - entering toolbox..."

        # Check if toolbox exists
        if ! toolbox list | grep -q "$TOOLBOX_NAME"; then
            echo "Creating toolbox: $TOOLBOX_NAME"
            toolbox create "$TOOLBOX_NAME"
        fi

        # Re-execute this script inside toolbox
        exec toolbox run -c "$TOOLBOX_NAME" "$0" "$@"
    fi

    # Now inside toolbox (or on non-Silverblue system)
    # ... rest of script ...
}

main "$@"
```

### Phase 2: Clean Flag (20 min)

```bash
clean() {
    echo "🧹 Cleaning WordPress installation..."

    # Stop containers
    if podman-compose ps | grep -q "Up"; then
        echo "  Stopping containers..."
        podman-compose down
    fi

    # Remove volumes
    echo "  Removing volumes..."
    podman volume rm wordpress_db wordpress_files 2>/dev/null || true

    echo "✅ Clean complete. Run ./inat.sh to start fresh."
}

if [[ "$1" == "--clean" ]]; then
    clean
    exit 0
fi
```

### Phase 3: WP-CLI Auto-Install (60 min)

```bash
clean_and_install() {
    clean

    echo "🚀 Starting fresh WordPress installation..."

    # Start containers
    podman-compose up -d

    # Wait for MySQL
    echo "  Waiting for MySQL to be ready..."
    sleep 10
    while ! podman exec mysql mysqladmin ping -h localhost --silent; do
        echo "  Still waiting for MySQL..."
        sleep 2
    done

    # Wait for WordPress
    echo "  Waiting for WordPress to be ready..."
    sleep 5

    # Generate random credentials
    RANDOM_SUFFIX=$(date +%s | sha256sum | head -c 8)
    ADMIN_USER="admin_${RANDOM_SUFFIX}"
    ADMIN_PASS="pass_$(openssl rand -hex 12)"
    SITE_TITLE="iNat Dev $(date '+%Y-%m-%d %H:%M')"

    # Install WordPress via WP-CLI
    echo "  Installing WordPress..."
    podman exec wordpress wp core install \
        --url="http://localhost:8080" \
        --title="$SITE_TITLE" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASS" \
        --admin_email="dev@localhost" \
        --skip-email \
        --allow-root

    # Activate plugin
    echo "  Activating iNat Observations plugin..."
    podman exec wordpress wp plugin activate inat-observations-wp --allow-root

    # Configure plugin
    echo "  Configuring plugin..."
    podman exec wordpress wp option update inat_obs_project_id "sdmyco" --allow-root

    # Run initial refresh (in background to avoid timeout)
    echo "  Triggering initial data refresh (background)..."
    podman exec wordpress wp cron event run inat_obs_refresh --allow-root &

    # Print credentials
    echo ""
    echo "✅ WordPress installed successfully!"
    echo ""
    echo "📋 Login Credentials:"
    echo "   URL:      http://localhost:8080/wp-admin"
    echo "   Username: $ADMIN_USER"
    echo "   Password: $ADMIN_PASS"
    echo ""
    echo "🔌 Plugin Status:"
    echo "   iNat Observations: Activated"
    echo "   Project:          sdmyco"
    echo "   Refresh:          Running in background..."
    echo ""

    # Optionally open browser
    if command -v xdg-open &> /dev/null; then
        read -p "Open browser? [y/N] " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            xdg-open "http://localhost:8080/wp-admin"
        fi
    fi
}

if [[ "$1" == "--clean-and-install" ]]; then
    clean_and_install
    exit 0
fi
```

### Phase 4: Default Behavior (15 min)

```bash
start() {
    echo "🐳 Starting WordPress development environment..."

    # Check if containers running
    if podman-compose ps | grep -q "Up"; then
        echo "✅ Containers already running"
    else
        echo "  Starting containers..."
        podman-compose up -d
        sleep 5
    fi

    echo ""
    echo "📍 WordPress:     http://localhost:8080"
    echo "📍 Admin:         http://localhost:8080/wp-admin"
    echo "📍 phpMyAdmin:    http://localhost:8081 (if configured)"
    echo ""
    echo "💡 Tips:"
    echo "   ./inat.sh --clean              Wipe all data"
    echo "   ./inat.sh --clean-and-install  Fresh install"
    echo "   ./inat.sh logs                 View logs"
    echo ""
}

# Default: start containers
if [[ $# -eq 0 ]]; then
    start
    exit 0
fi
```

## Testing Scenarios

1. **Silverblue Auto-Toolbox**
   - Run on Silverblue system
   - Verify toolbox auto-created
   - Verify commands run inside toolbox

2. **Clean Flag**
   - Start containers
   - Run `./inat.sh --clean`
   - Verify volumes removed
   - Verify containers stopped

3. **Clean-and-Install Flag**
   - Run `./inat.sh --clean-and-install`
   - Verify fresh WordPress installed
   - Verify random credentials printed
   - Verify plugin activated
   - Verify sdmyco project configured

4. **Default Start**
   - Run `./inat.sh`
   - Verify containers start if stopped
   - Verify status printed

## Edge Cases

- **No podman-compose**: Print error, suggest installation
- **No WP-CLI in container**: Print error, update Dockerfile
- **MySQL connection fails**: Retry with exponential backoff
- **Plugin activation fails**: Print error, continue anyway
- **Toolbox creation fails**: Print error with troubleshooting steps

## Current State

Currently, `inat.sh` doesn't exist yet. We need to create it from scratch.

## Related Files

- `docker-compose.yml` - Container orchestration config
- `Dockerfile` - WordPress container image (may need WP-CLI)
- `wp-content/plugins/inat-observations-wp/` - Plugin directory

## Notes

- WP-CLI must be installed in WordPress container (update Dockerfile if needed)
- Podman-compose syntax is mostly compatible with docker-compose
- On non-Silverblue systems, script runs directly (no toolbox)
- Credentials are ephemeral - only for current dev session
- Background refresh may take 30-60 seconds for large projects
