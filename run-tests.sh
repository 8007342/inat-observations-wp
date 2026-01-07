#!/bin/bash
#
# run-tests.sh - Run PHPUnit tests in toolbox
#
# Automatically enters toolbox, installs dependencies, and runs tests.
#

set -e

TOOLBOX_NAME="inat-observations"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if running inside toolbox
is_inside_toolbox() {
    [[ -f /run/.containerenv ]] || [[ -f /run/.toolboxenv ]]
}

# Check if toolbox exists
toolbox_exists() {
    toolbox list 2>/dev/null | grep -qw "$TOOLBOX_NAME"
}

# If not in toolbox, re-exec inside toolbox
if ! is_inside_toolbox; then
    echo "🏠 On host - entering toolbox '$TOOLBOX_NAME'..."

    # Create toolbox if needed
    if ! toolbox_exists; then
        echo "📦 Creating toolbox '$TOOLBOX_NAME'..."
        toolbox create "$TOOLBOX_NAME"
    fi

    # Re-execute inside toolbox
    exec toolbox run -c "$TOOLBOX_NAME" "$0" "$@"
fi

# Now inside toolbox
echo "📦 Inside toolbox '$TOOLBOX_NAME'"

# Install dependencies if needed
if ! command -v php &> /dev/null || ! command -v composer &> /dev/null; then
    echo "🔧 Installing PHP and Composer..."
    sudo dnf install -y php php-cli php-json php-xml php-mbstring php-mysqlnd composer 2>&1 | grep -E "(Installing|Installed|Complete)" || true
fi

# Install Composer dependencies if vendor/ is missing
if [ ! -d "$PROJECT_ROOT/vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    cd "$PROJECT_ROOT"
    composer install
fi

# Check WordPress test library
if [ ! -d "/tmp/wordpress-tests-lib" ] && [ -z "$WP_TESTS_DIR" ]; then
    echo "⚠️  WordPress test library not found"
    echo "   The integration tests require WordPress test environment."
    echo ""
    echo "   For now, running unit tests only..."
    echo ""

    # Run unit tests only (with Brain\Monkey, no WordPress needed)
    cd "$PROJECT_ROOT"
    TEST_TYPE=unit ./vendor/bin/phpunit tests/unit/
    exit 0
fi

# Run all tests (integration + unit)
cd "$PROJECT_ROOT"
echo "🧪 Running all tests..."
./vendor/bin/phpunit

echo ""
echo "✅ Tests complete!"
echo ""
echo "To generate coverage report:"
echo "  ./vendor/bin/phpunit --coverage-html dashboard/coverage"
