#!/usr/bin/env bash
#
# inat.sh - iNaturalist Observations WordPress Plugin Development Environment
#
# Single point of entry for development. Sets up toolbox, installs dependencies,
# and launches Docker Compose stack.
#
# Usage:
#   ./inat.sh           # Start dev environment
#   ./inat.sh --stop    # Stop containers
#   ./inat.sh --clean   # Stop and remove volumes (DESTRUCTIVE)

set -euo pipefail

# === Configuration ===
TOOLBOX_NAME="inat-observations"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMPOSE_FILE="$PROJECT_ROOT/docker-compose.yml"

# === Functions ===

# Check if running inside toolbox
is_inside_toolbox() {
    [[ -f /run/.containerenv ]] || [[ -f /run/.toolboxenv ]]
}

# Check if toolbox exists
toolbox_exists() {
    toolbox list 2>/dev/null | grep -qw "$TOOLBOX_NAME"
}

# Create toolbox
create_toolbox() {
    echo "📦 Creating toolbox '$TOOLBOX_NAME'..."
    if toolbox create "$TOOLBOX_NAME" 2>&1 | grep -q "already exists"; then
        echo "ℹ️  Toolbox already exists, using existing one"
    fi
}

# Install Docker and dependencies
install_dependencies() {
    echo "🔧 Checking dependencies..."

    local needs_install=false

    # Check Docker CLI
    if ! command -v docker &> /dev/null; then
        needs_install=true
        echo "  → Docker CLI not found"
    fi

    # Check docker-compose
    if ! command -v docker-compose &> /dev/null; then
        needs_install=true
        echo "  → docker-compose not found"
    fi

    # Check PHP
    if ! command -v php &> /dev/null; then
        needs_install=true
        echo "  → PHP not found"
    fi

    # Check Composer
    if ! command -v composer &> /dev/null; then
        needs_install=true
        echo "  → Composer not found"
    fi

    # Check WP-CLI
    if ! command -v wp &> /dev/null; then
        needs_install=true
        echo "  → WP-CLI not found"
    fi

    if [ "$needs_install" = false ]; then
        echo "✅ All dependencies already installed"
        return 0
    fi

    echo "📥 Installing missing dependencies..."
    echo "  (You may be prompted for your password)"

    # Update package list (quietly)
    sudo dnf update -y -q 2>&1 | grep -v "^Last metadata" || true

    # Install Docker CLI and docker-compose (NOT the daemon - we use host's daemon)
    if ! command -v docker &> /dev/null || ! command -v docker-compose &> /dev/null; then
        echo "  → Installing Docker CLI and docker-compose..."
        sudo dnf install -y -q docker-compose 2>&1 | grep -E "(Installing|Installed|Complete)" || true
    fi

    # Install PHP development tools
    if ! command -v php &> /dev/null; then
        echo "  → Installing PHP and development tools..."
        sudo dnf install -y -q \
            php \
            php-cli \
            php-mysqlnd \
            php-json \
            php-xml \
            php-mbstring \
            php-zip \
            php-gd \
            php-curl \
            composer 2>&1 | grep -E "(Installing|Installed|Complete)" || true
    fi

    # Install MySQL client for debugging
    if ! command -v mysql &> /dev/null; then
        echo "  → Installing MySQL client..."
        sudo dnf install -y -q mysql 2>&1 | grep -E "(Installing|Installed|Complete)" || true
    fi

    # Install WordPress CLI
    if ! command -v wp &> /dev/null; then
        echo "  → Installing WP-CLI..."
        curl -sS -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar 2>/dev/null
        chmod +x wp-cli.phar
        sudo mv wp-cli.phar /usr/local/bin/wp
    fi

    echo "✅ Dependencies installed"
}

# Fix SELinux permissions for Docker volumes
fix_selinux_permissions() {
    if ! command -v chcon &> /dev/null; then
        # Not on SELinux system, skip
        return 0
    fi

    # Create volume directories if they don't exist
    mkdir -p "$PROJECT_ROOT/docker-volumes/mysql"
    mkdir -p "$PROJECT_ROOT/docker-volumes/wordpress"

    # Set SELinux context (svirt_sandbox_file_t allows container access)
    # Use chcon from host if available, otherwise skip (Docker :Z flag will handle it)
    if sudo chcon -Rt svirt_sandbox_file_t "$PROJECT_ROOT/docker-volumes/" 2>/dev/null; then
        echo "✅ SELinux permissions configured"
    else
        echo "ℹ️  SELinux relabeling skipped (Docker :Z flag will handle it)"
    fi
}

# Start Docker Compose stack
start_stack() {
    echo "🚀 Starting WordPress + MySQL stack..."
    echo ""
    echo "  WordPress: http://localhost:8080"
    echo "  Admin:     http://localhost:8080/wp-admin"
    echo ""
    echo "  Default credentials (first time setup):"
    echo "    Username: admin"
    echo "    Password: admin"
    echo ""
    echo "Press Ctrl+C to stop"
    echo ""

    cd "$PROJECT_ROOT"

    # Run docker-compose up with logs to STDOUT
    # Docker automatically prefixes logs with service name
    docker-compose up
}

# Stop Docker Compose stack
stop_stack() {
    echo "🛑 Stopping containers..."
    cd "$PROJECT_ROOT"
    docker-compose down
    echo "✅ Containers stopped"
}

# Clean Docker Compose stack (DESTRUCTIVE)
clean_stack() {
    echo "⚠️  WARNING: This will delete all data (MySQL database, WordPress files)"
    read -p "Are you sure? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🗑️  Removing containers and volumes..."
        cd "$PROJECT_ROOT"
        docker-compose down -v
        rm -rf "$PROJECT_ROOT/docker-volumes/"
        echo "✅ Cleaned"
    else
        echo "Cancelled"
    fi
}

# === Main ===

# Parse arguments
case "${1:-}" in
    --stop)
        if is_inside_toolbox; then
            stop_stack
        else
            toolbox run -c "$TOOLBOX_NAME" "$0" --stop
        fi
        exit 0
        ;;
    --clean)
        if is_inside_toolbox; then
            clean_stack
        else
            toolbox run -c "$TOOLBOX_NAME" "$0" --clean
        fi
        exit 0
        ;;
    --help|-h)
        echo "Usage: ./inat.sh [OPTION]"
        echo ""
        echo "Options:"
        echo "  (none)    Start development environment"
        echo "  --stop    Stop Docker containers"
        echo "  --clean   Stop and remove all data (DESTRUCTIVE)"
        echo "  --help    Show this help message"
        exit 0
        ;;
esac

# Check if inside toolbox
if ! is_inside_toolbox; then
    echo "🏠 Running on host - entering toolbox..."

    # Create toolbox if needed
    if ! toolbox_exists; then
        create_toolbox
    fi

    # Re-execute script inside toolbox
    exec toolbox run -c "$TOOLBOX_NAME" "$PROJECT_ROOT/inat.sh" "$@"
fi

# Now inside toolbox
echo "📦 Inside toolbox '$TOOLBOX_NAME'"

# Install dependencies
install_dependencies

# Fix SELinux permissions
fix_selinux_permissions

# Check if Docker daemon is accessible
# In a toolbox, we use the host's Docker daemon via /var/run/docker.sock
echo "🐳 Checking Docker daemon..."
if docker info &>/dev/null; then
    echo "✅ Docker daemon accessible"
else
    echo "❌ Cannot connect to Docker daemon"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. On the HOST (outside toolbox), ensure Docker is running:"
    echo "   sudo systemctl start docker"
    echo "   sudo systemctl enable docker"
    echo ""
    echo "2. On the HOST, add your user to docker group:"
    echo "   sudo usermod -aG docker $USER"
    echo "   newgrp docker"
    echo ""
    echo "3. Exit and re-enter the toolbox:"
    echo "   exit"
    echo "   toolbox enter inat-observations"
    echo ""
    exit 1
fi

# Start stack
start_stack
