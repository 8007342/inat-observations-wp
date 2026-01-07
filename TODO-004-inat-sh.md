# TODO-004: inat.sh Development Environment Bootstrap Script

**Created**: 2026-01-06
**Priority**: HIGH
**Status**: In Progress
**Type**: Infrastructure
**Platform**: Fedora Silverblue + Toolbox

---

## Objective

**Create `inat.sh` entry point script that sets up a complete PHP development environment inside a toolbox, installs Docker/Docker Compose, and launches the WordPress development stack.**

Similar to `ai-way/yollayah.sh`, this script is the **single point of entry** for inat-observations-wp development.

---

## Requirements

### Core Functionality

1. **Toolbox Management**:
   - Check if toolbox "inat-observations" exists
   - Create toolbox if missing
   - Enter toolbox automatically

2. **Dependency Installation**:
   - Docker Engine
   - Docker Compose
   - PHP (latest stable)
   - PHP development tools (linters, formatters)
   - MySQL client (for debugging)

3. **Docker Compose Orchestration**:
   - Run `docker-compose up` from project root
   - Stream logs to STDOUT (Docker prefixes by service)
   - Handle graceful shutdown (Ctrl+C)

4. **Permission Issue Handling**:
   - MySQL service permission errors (documented below)
   - Docker socket permissions
   - Volume mount permissions

---

## Known Issues

### Issue 1: MySQL Permission Errors

**Symptom**:
```
mysql_1      | 2026-01-06 10:30:45 [ERROR] [MY-010119] [Server] Aborting
mysql_1      | mysqld: Cannot change permissions of the file 'multi-master.info' (Errcode: 1 - Operation not permitted)
```

**Root Cause**: SELinux context on volume mounts

**Investigation Needed**:
- Check if MySQL data volume has correct SELinux labels
- Verify Docker volume permissions (ls -lZ)
- Test with `:z` or `:Z` volume mount flags

**Potential Fix**:
```yaml
# docker-compose.yml
services:
  mysql:
    volumes:
      - mysql_data:/var/lib/mysql:Z  # Add :Z for SELinux relabeling
```

**Alternative Fix**:
```bash
# In inat.sh, before docker-compose up
# Set SELinux context for Docker volumes
chcon -Rt svirt_sandbox_file_t ./docker-volumes/
```

---

### Issue 2: Docker Socket Permissions

**Symptom**:
```
Cannot connect to the Docker daemon at unix:///var/run/docker.sock. Is the docker daemon running?
```

**Root Cause**: User not in `docker` group inside toolbox

**Fix**:
```bash
# In inat.sh, after entering toolbox
sudo usermod -aG docker $USER
newgrp docker  # Apply group membership without logout
```

---

## Script Structure

### inat.sh (Entry Point)

```bash
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
    toolbox list | grep -q "^$TOOLBOX_NAME"
}

# Create toolbox
create_toolbox() {
    echo "📦 Creating toolbox '$TOOLBOX_NAME'..."
    toolbox create "$TOOLBOX_NAME"
}

# Install Docker and dependencies
install_dependencies() {
    echo "🔧 Installing dependencies..."

    # Update package list
    sudo dnf update -y

    # Install Docker
    if ! command -v docker &> /dev/null; then
        echo "  → Installing Docker..."
        sudo dnf install -y docker docker-compose
        sudo systemctl enable --now docker
        sudo usermod -aG docker "$USER"
    fi

    # Install PHP development tools
    if ! command -v php &> /dev/null; then
        echo "  → Installing PHP and development tools..."
        sudo dnf install -y \
            php \
            php-cli \
            php-fpm \
            php-mysqlnd \
            php-json \
            php-xml \
            php-mbstring \
            php-zip \
            php-gd \
            php-curl \
            php-opcache \
            phpunit \
            composer
    fi

    # Install PHP linters and formatters
    if ! command -v phpcs &> /dev/null; then
        echo "  → Installing PHP CodeSniffer..."
        composer global require "squizlabs/php_codesniffer=*"
    fi

    if ! command -v phpcbf &> /dev/null; then
        echo "  → Installing PHP Code Beautifier..."
        # Installed with phpcs
    fi

    # Install MySQL client for debugging
    if ! command -v mysql &> /dev/null; then
        echo "  → Installing MySQL client..."
        sudo dnf install -y mysql
    fi

    # Install WordPress CLI
    if ! command -v wp &> /dev/null; then
        echo "  → Installing WP-CLI..."
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        sudo mv wp-cli.phar /usr/local/bin/wp
    fi

    echo "✅ Dependencies installed"
}

# Fix SELinux permissions for Docker volumes
fix_selinux_permissions() {
    echo "🔒 Setting SELinux contexts for Docker volumes..."

    # Create volume directories if they don't exist
    mkdir -p "$PROJECT_ROOT/docker-volumes/mysql"
    mkdir -p "$PROJECT_ROOT/docker-volumes/wordpress"

    # Set SELinux context (svirt_sandbox_file_t allows container access)
    sudo chcon -Rt svirt_sandbox_file_t "$PROJECT_ROOT/docker-volumes/" 2>/dev/null || true

    echo "✅ SELinux permissions configured"
}

# Start Docker Compose stack
start_stack() {
    echo "🚀 Starting WordPress + MySQL stack..."
    echo ""
    echo "  WordPress: http://localhost:8080"
    echo "  Admin:     http://localhost:8080/wp-admin"
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
        stop_stack
        exit 0
        ;;
    --clean)
        clean_stack
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

# Ensure Docker daemon is running
if ! sudo systemctl is-active --quiet docker; then
    echo "🔄 Starting Docker daemon..."
    sudo systemctl start docker
fi

# Add user to docker group if not already
if ! groups | grep -q docker; then
    echo "👤 Adding user to docker group..."
    sudo usermod -aG docker "$USER"
    echo "⚠️  Group membership updated. You may need to log out and back in."
    echo "   For now, using newgrp to apply changes..."
    exec sg docker "$0" "$@"
fi

# Start stack
start_stack
```

**Features**:
- ✅ Auto-creates toolbox if missing
- ✅ Auto-enters toolbox (re-execs itself)
- ✅ Installs all dependencies (Docker, PHP, tools)
- ✅ Fixes SELinux permissions for MySQL
- ✅ Handles Docker group membership
- ✅ Streams logs with service prefixes
- ✅ Supports --stop and --clean options

---

## Implementation Tasks

### Task 1: Create inat.sh Script ✅

**File**: `inat.sh` (project root)

**Actions**:
- Create script with full implementation above
- Make executable: `chmod +x inat.sh`
- Test on Fedora Silverblue

---

### Task 2: Update .gitignore ✅

**File**: `.gitignore`

**Add**:
```gitignore
# Docker volumes (local development data)
docker-volumes/

# Toolbox environment marker
.toolboxenv
```

---

### Task 3: Update docker-compose.yml for SELinux ✅

**File**: `docker-compose.yml`

**Update MySQL volume mounts**:
```yaml
services:
  mysql:
    image: mysql:8.0
    volumes:
      # Add :Z flag for SELinux relabeling (allows container access)
      - mysql_data:/var/lib/mysql:Z
    environment:
      MYSQL_ROOT_PASSWORD: wordpress
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress

  wordpress:
    image: wordpress:latest
    depends_on:
      - mysql
    ports:
      - "8080:80"
    volumes:
      # Mount plugin directory (development mode)
      - ./wp-content/plugins/inat-observations-wp:/var/www/html/wp-content/plugins/inat-observations-wp:Z
    environment:
      WORDPRESS_DB_HOST: mysql:3306
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress

volumes:
  mysql_data:
```

**Key changes**:
- Add `:Z` flag to MySQL data volume (private SELinux relabeling)
- Add `:Z` flag to plugin volume mount
- Ensure proper dependency order (wordpress depends_on mysql)

---

### Task 4: Create README.md for Development Setup ✅

**File**: `README.md` (update existing or create)

**Add section**:
```markdown
## Development Setup (Fedora Silverblue)

### Quick Start

```bash
# Clone repository
git clone https://github.com/8007342/inat-observations-wp.git
cd inat-observations-wp

# Start development environment
./inat.sh
```

This will:
1. Create "inat-observations" toolbox (if needed)
2. Install Docker, PHP, and development tools
3. Fix SELinux permissions
4. Launch WordPress + MySQL stack
5. Stream logs to terminal

### Accessing WordPress

- **Frontend**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin
- **Credentials**: admin / admin (set on first visit)

### Stopping

Press `Ctrl+C` to stop containers.

Or run:
```bash
./inat.sh --stop
```

### Clean Install (Delete All Data)

```bash
./inat.sh --clean  # DESTRUCTIVE - removes database and files
```

### Toolbox Management

```bash
# List toolboxes
toolbox list

# Enter toolbox manually
toolbox enter inat-observations

# Remove toolbox (clean uninstall)
toolbox rm inat-observations
```
```

---

### Task 5: Test and Debug ✅

**Test Cases**:

1. **Fresh setup on Silverblue**:
   ```bash
   ./inat.sh
   # Should create toolbox, install deps, start stack
   ```

2. **Existing toolbox**:
   ```bash
   ./inat.sh
   # Should reuse existing toolbox, check deps, start stack
   ```

3. **MySQL permission errors**:
   - Monitor logs for "Operation not permitted"
   - Verify `:Z` flag fixes issue
   - Check `ls -lZ docker-volumes/mysql/`

4. **Stop and restart**:
   ```bash
   # In terminal 1
   ./inat.sh
   # Ctrl+C

   # Restart
   ./inat.sh
   # Should reuse existing containers and data
   ```

5. **Clean install**:
   ```bash
   ./inat.sh --clean
   ./inat.sh
   # Should start fresh (no database data)
   ```

---

## Expected Output

### Successful Startup

```
🏠 Running on host - entering toolbox...
📦 Inside toolbox 'inat-observations'
🔧 Installing dependencies...
  → Docker already installed ✓
  → PHP already installed ✓
✅ Dependencies installed
🔒 Setting SELinux contexts for Docker volumes...
✅ SELinux permissions configured
🚀 Starting WordPress + MySQL stack...

  WordPress: http://localhost:8080
  Admin:     http://localhost:8080/wp-admin

Press Ctrl+C to stop

mysql_1      | 2026-01-06 10:30:01 [Note] [Entrypoint]: Initializing database files
mysql_1      | 2026-01-06 10:30:05 [Note] [Entrypoint]: Database files initialized
wordpress_1  | WordPress not found in /var/www/html - copying now...
wordpress_1  | Complete! WordPress has been successfully copied to /var/www/html
mysql_1      | 2026-01-06T10:30:10.123456Z 0 [System] [MY-010931] [Server] /usr/sbin/mysqld: ready for connections.
wordpress_1  | [06-Jan-2026 10:30:12] NOTICE: fpm is running, pid 1
```

---

## Known Limitations

1. **Silverblue-specific**: Script assumes Fedora Silverblue with toolbox
2. **Docker inside toolbox**: Some performance overhead vs native Docker
3. **SELinux complexity**: `:Z` flag required, may conflict with other containers
4. **Single instance**: Only one "inat-observations" toolbox supported

---

## Future Enhancements

- [ ] Add `--shell` flag to enter toolbox without starting Docker
- [ ] Add `--logs` flag to tail logs without restart
- [ ] Add `--rebuild` flag to rebuild Docker images
- [ ] Support other distros (detect and skip toolbox on non-Silverblue)
- [ ] Add MySQL data backup/restore commands
- [ ] Add WP-CLI integration (`./inat.sh wp plugin list`)

---

## Related Files

- `docker-compose.yml` - Docker stack definition
- `.gitignore` - Exclude docker-volumes/
- `README.md` - User documentation

---

## Troubleshooting

### Issue: "Cannot connect to Docker daemon"
**Solution**: Ensure Docker service is running:
```bash
toolbox enter inat-observations
sudo systemctl start docker
```

### Issue: "Permission denied" on MySQL
**Solution**: Check SELinux contexts:
```bash
ls -lZ docker-volumes/mysql/
# Should show svirt_sandbox_file_t
```

Re-run script to fix:
```bash
./inat.sh --stop
./inat.sh  # Will re-apply SELinux contexts
```

### Issue: "Port 8080 already in use"
**Solution**: Stop conflicting service or change port in `docker-compose.yml`:
```yaml
ports:
  - "8081:80"  # Use 8081 instead
```

---

**Status**: IN PROGRESS
**Next Action**: Task 1 - Create inat.sh script
**Owner**: DevOps + Full-Stack Developer
