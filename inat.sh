#!/bin/bash
#
# inat.sh - WordPress development environment for iNat Observations plugin
#
# Usage:
#   ./inat.sh                    Start WordPress (or enter if stopped)
#   ./inat.sh --clean            Stop containers and wipe all data
#   ./inat.sh --clean-and-install Clean data and auto-install WordPress
#   ./inat.sh logs               Tail container logs
#

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOLBOX_NAME="inat-observations"

# Detect Fedora Silverblue
is_silverblue() {
    if [[ -f /etc/os-release ]]; then
        grep -q "^VARIANT_ID=silverblue" /etc/os-release 2>/dev/null
    else
        return 1
    fi
}

# Check if inside toolbox
is_inside_toolbox() {
    [[ -f /run/.containerenv ]] || [[ -f /run/.toolboxenv ]]
}

# Clean: stop containers and remove volumes
clean() {
    echo "🧹 Cleaning WordPress installation..."

    cd "$PROJECT_ROOT"

    # Stop containers
    if podman-compose ps 2>/dev/null | grep -q "Up"; then
        echo "  Stopping containers..."
        podman-compose down
    else
        echo "  Containers not running"
    fi

    # Remove volumes
    echo "  Removing volumes..."
    podman volume rm wordpress_db wordpress_files 2>/dev/null || echo "  No volumes to remove"

    echo "✅ Clean complete."
}

# Clean and install WordPress automatically
clean_and_install() {
    echo "🚀 Starting fresh WordPress installation..."

    clean

    cd "$PROJECT_ROOT"

    # Start containers
    echo "  Starting containers..."
    podman-compose up -d

    # Wait for MySQL
    echo "  Waiting for MySQL to be ready..."
    local retries=0
    local max_retries=30
    while ! podman exec mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
        retries=$((retries + 1))
        if [[ $retries -ge $max_retries ]]; then
            echo "❌ MySQL failed to start after ${max_retries} seconds"
            exit 1
        fi
        sleep 1
    done
    echo "  MySQL ready!"

    # Wait for WordPress
    echo "  Waiting for WordPress to be ready..."
    sleep 5

    # Generate random credentials
    RANDOM_SUFFIX=$(date +%s | sha256sum | head -c 8)
    ADMIN_USER="admin_${RANDOM_SUFFIX}"
    ADMIN_PASS="pass_$(openssl rand -hex 12)"
    SITE_TITLE="iNat Dev $(date '+%Y-%m-%d %H:%M')"

    # Check if WP-CLI is available
    if ! podman exec wordpress wp --version --allow-root &>/dev/null; then
        echo "❌ WP-CLI not found in WordPress container"
        echo "   Please install WP-CLI in the WordPress container"
        echo "   Container is running at http://localhost:8080"
        echo "   Complete manual installation, then use this script"
        exit 1
    fi

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
    podman exec wordpress wp plugin activate inat-observations-wp --allow-root || {
        echo "  Warning: Plugin activation failed (may not be symlinked yet)"
    }

    # Configure plugin
    echo "  Configuring plugin..."
    podman exec wordpress wp option update inat_obs_project_id "sdmyco" --allow-root
    podman exec wordpress wp option update inat_obs_refresh_rate "daily" --allow-root
    podman exec wordpress wp option update inat_obs_api_fetch_size "2000" --allow-root
    podman exec wordpress wp option update inat_obs_display_page_size "50" --allow-root

    # Run initial refresh (in background to avoid timeout)
    echo "  Triggering initial data refresh (background)..."
    podman exec wordpress wp cron event run inat_obs_refresh --allow-root &>/dev/null &

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
    echo "   iNat Observations: Activated (if symlinked)"
    echo "   Project:          sdmyco"
    echo "   Refresh Rate:     Daily"
    echo "   API Fetch Size:   2000"
    echo "   Display Size:     50"
    echo ""
    echo "📝 Next Steps:"
    echo "   1. Open http://localhost:8080/wp-admin in your browser"
    echo "   2. Log in with the credentials above"
    echo "   3. Go to Settings → iNat Observations"
    echo "   4. Click 'Refresh Now' to fetch observations"
    echo "   5. Create a page with [inat_observations] shortcode"
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

# Start containers
start() {
    echo "🐳 Starting WordPress development environment..."

    cd "$PROJECT_ROOT"

    # Check if containers running
    if podman-compose ps 2>/dev/null | grep -q "Up"; then
        echo "✅ Containers already running"
    else
        echo "  Starting containers..."
        podman-compose up -d
        sleep 3
    fi

    echo ""
    echo "📍 WordPress:     http://localhost:8080"
    echo "📍 Admin:         http://localhost:8080/wp-admin"
    echo "📍 Plugin:        wp-content/plugins/inat-observations-wp/"
    echo ""
    echo "⚙️  Configuration:"
    echo "   PHP:          docker/php.ini (512MB memory, 5min execution)"
    echo "   MySQL:        docker/mysql.cnf (64MB packets, 256MB buffer)"
    echo "   Note:         Recreate containers if configs change"
    echo ""
    echo "💡 Commands:"
    echo "   ./inat.sh --clean              Wipe all data"
    echo "   ./inat.sh --clean-and-install  Fresh install with auto-config"
    echo "   ./inat.sh logs                 View container logs"
    echo ""
}

# Show logs
show_logs() {
    cd "$PROJECT_ROOT"
    echo "📜 Showing container logs (Ctrl+C to exit)..."
    podman-compose logs -f
}

# Main entry point
main() {
    # If on Silverblue and not inside toolbox, re-exec inside toolbox
    if is_silverblue && ! is_inside_toolbox; then
        echo "🔧 Detected Fedora Silverblue - entering toolbox..."

        # Check if toolbox exists
        if ! toolbox list 2>/dev/null | grep -q "$TOOLBOX_NAME"; then
            echo "  Creating toolbox: $TOOLBOX_NAME"
            toolbox create "$TOOLBOX_NAME"
        fi

        # Re-execute this script inside toolbox
        exec toolbox run -c "$TOOLBOX_NAME" "$0" "$@"
    fi

    # Now inside toolbox (or on non-Silverblue system)
    cd "$PROJECT_ROOT"

    # Check for podman-compose
    if ! command -v podman-compose &> /dev/null; then
        echo "❌ podman-compose not found"
        echo "   Install it with: pip install podman-compose"
        echo "   Or on Fedora: sudo dnf install podman-compose"
        exit 1
    fi

    # Parse command
    case "${1:-}" in
        --clean)
            clean
            ;;
        --clean-and-install)
            clean_and_install
            ;;
        logs)
            show_logs
            ;;
        "")
            start
            ;;
        *)
            echo "❌ Unknown command: $1"
            echo ""
            echo "Usage:"
            echo "  ./inat.sh                    Start WordPress"
            echo "  ./inat.sh --clean            Wipe all data"
            echo "  ./inat.sh --clean-and-install Fresh install"
            echo "  ./inat.sh logs               View logs"
            exit 1
            ;;
    esac
}

main "$@"
