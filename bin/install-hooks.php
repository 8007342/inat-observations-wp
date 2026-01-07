#!/usr/bin/env php
<?php
/**
 * Install Git Hooks
 *
 * Installs pre-commit hook that automatically regenerates dashboard on commit.
 *
 * Usage:
 *   php bin/install-hooks.php
 *   composer install-hooks
 */

$rootDir = dirname(__DIR__);
$hookSource = __DIR__ . '/git-hooks/pre-commit';
$hookDest = $rootDir . '/.git/hooks/pre-commit';

echo "Installing git hooks...\n";

// Create pre-commit hook content
$hookContent = <<<'BASH'
#!/bin/bash
#
# Pre-commit hook: Auto-generate dashboard metrics
#

set -e

echo "🔍 Running pre-commit checks..."

# Run tests with coverage
echo "  → Running tests with coverage..."
composer test:coverage --quiet || {
    echo "❌ Tests failed - commit aborted"
    exit 1
}

# Generate metrics
echo "  → Generating metrics..."
composer metrics:generate --quiet

# Snapshot metrics to history
TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")
COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "uncommitted")

mkdir -p dashboard/history
cp dashboard/metrics.json "dashboard/history/${TIMESTAMP}-${COMMIT}-metrics.json"

# Stage dashboard artifacts
git add dashboard/metrics.json 2>/dev/null || true
git add dashboard/metrics.md 2>/dev/null || true
git add dashboard/history/ 2>/dev/null || true

# Check quality gates
COVERAGE=$(php -r "echo json_decode(file_get_contents('dashboard/metrics.json'))->coverage->line_percentage;")
WARNINGS=$(php -r "echo json_decode(file_get_contents('dashboard/metrics.json'))->quality->warnings;")
ERRORS=$(php -r "echo json_decode(file_get_contents('dashboard/metrics.json'))->quality->errors;")

echo "  → Coverage: ${COVERAGE}%"
echo "  → Warnings: ${WARNINGS}"
echo "  → Errors: ${ERRORS}"

# Enforce quality gates (comment out to make non-blocking)
# if (( $(echo "$COVERAGE < 97" | bc -l) )); then
#     echo "❌ Coverage below 97% - commit aborted"
#     echo "   Run 'composer test:coverage' and add more tests"
#     exit 1
# fi

# if [ "$WARNINGS" -gt 0 ] || [ "$ERRORS" -gt 0 ]; then
#     echo "❌ Code quality issues found - commit aborted"
#     echo "   Run 'composer lint:fix' to auto-fix issues"
#     exit 1
# fi

echo "✅ Pre-commit checks passed"

BASH;

// Ensure .git/hooks directory exists
$hooksDir = dirname($hookDest);
if (!is_dir($hooksDir)) {
    mkdir($hooksDir, 0755, true);
}

// Write hook
file_put_contents($hookDest, $hookContent);
chmod($hookDest, 0755);

echo "✓ Installed: .git/hooks/pre-commit\n";
echo "\n";
echo "Pre-commit hook configured:\n";
echo "  - Runs tests with coverage\n";
echo "  - Generates dashboard metrics\n";
echo "  - Snapshots metrics to history/\n";
echo "  - Stages dashboard artifacts\n";
echo "\n";
echo "Quality gates (currently disabled):\n";
echo "  - Coverage ≥ 97%\n";
echo "  - Warnings = 0\n";
echo "  - Errors = 0\n";
echo "\n";
echo "To enable blocking gates, edit .git/hooks/pre-commit\n";
echo "and uncomment the enforcement section.\n";
echo "\n";
echo "✅ Git hooks installed\n";
