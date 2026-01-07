#!/bin/bash
#
# debug-tests.sh - Debug test setup
#

set -e

TOOLBOX_NAME="inat-observations"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if inside toolbox
is_inside_toolbox() {
    [[ -f /run/.containerenv ]] || [[ -f /run/.toolboxenv ]]
}

if ! is_inside_toolbox; then
    echo "Re-executing inside toolbox..."
    exec toolbox run -c "$TOOLBOX_NAME" "$0" "$@"
fi

echo "=== Test Environment Debug ==="
echo ""

echo "1. PHP version:"
php --version
echo ""

echo "2. Composer version:"
composer --version 2>&1 | head -1
echo ""

echo "3. PHPUnit version:"
./vendor/bin/phpunit --version
echo ""

echo "4. Test files found:"
find tests/unit -name "*.php" -type f
echo ""

echo "5. Bootstrap file exists:"
ls -la tests/bootstrap.php
echo ""

echo "6. Brain\Monkey installed:"
ls vendor/brain/monkey 2>&1 | head -3
echo ""

echo "7. Running bootstrap manually:"
cd "$PROJECT_ROOT"
TEST_TYPE=unit php tests/bootstrap.php
echo "Bootstrap executed successfully!"
echo ""

echo "8. Trying to load test-simple.php:"
TEST_TYPE=unit php -r "
require 'tests/bootstrap.php';
require 'tests/unit/test-simple.php';
echo 'test-simple.php loaded successfully!\n';
"
echo ""

echo "9. PHPUnit list-tests:"
cd "$PROJECT_ROOT"
TEST_TYPE=unit ./vendor/bin/phpunit --list-tests tests/unit/
echo ""

echo "10. PHPUnit configuration check:"
./vendor/bin/phpunit --configuration tests/phpunit.xml --list-tests
echo ""

echo "=== Debug complete ==="
