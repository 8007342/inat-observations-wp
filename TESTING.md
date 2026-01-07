# Testing Guide - inat-observations-wp

**Environment:** Fedora Silverblue with Toolbox

---

## Quick Start

```bash
# From the project root (on host)
./run-tests.sh
```

This script will:
1. ✅ Automatically enter the toolbox
2. ✅ Install PHP and Composer if needed
3. ✅ Install test dependencies
4. ✅ Run the test suite

---

## Manual Testing (Inside Toolbox)

If you prefer manual control:

### 1. Enter Toolbox

```bash
# From anywhere on the host
toolbox enter inat-observations
```

### 2. Install Dependencies (First Time Only)

```bash
# Inside toolbox
sudo dnf install -y php php-cli php-json php-xml php-mbstring php-mysqlnd composer

# Install Composer dependencies
cd /var/home/machiyotl/src/inat-observations-wp
composer install
```

### 3. Run Tests

```bash
# All tests
composer test

# Unit tests only (fast, no WordPress needed)
TEST_TYPE=unit ./vendor/bin/phpunit tests/unit/

# Integration tests only (requires WordPress test library)
./vendor/bin/phpunit tests/integration/

# Specific test file
./vendor/bin/phpunit tests/unit/test-api.php

# With coverage report
composer test:coverage
open dashboard/coverage/index.html
```

---

## Understanding the Test Environment

### Two Test Modes

**Unit Tests** (`tests/unit/`)
- ✅ Fast (< 100ms per test)
- ✅ Uses Brain\Monkey to mock WordPress functions
- ✅ No WordPress installation required
- ✅ Perfect for TDD and rapid iteration

**Integration Tests** (`tests/integration/`)
- ⚠️ Slower (< 1s per test)
- ⚠️ Requires WordPress test library
- ✅ Tests with real WordPress environment
- ✅ Validates database schema, hooks, etc.

### Bootstrap Behavior

The test bootstrap (`tests/bootstrap.php`) automatically detects which mode to use:

```php
// Runs unit tests if:
- TEST_TYPE=unit is set, OR
- WordPress test library is not installed

// Runs integration tests if:
- WordPress test library is available at:
  - $WP_TESTS_DIR, OR
  - /tmp/wordpress-tests-lib
```

---

## Current Test Status

**Tests Implemented:**
- ✅ `test-db-schema.php` - 9 tests (database operations)
- ✅ `test-api.php` - 13 tests (API fetching with mocks)
- ✅ `test-activation.php` - 1 test (plugin activation)

**Coverage:**
- **Estimated:** ~30% line coverage
- **Target:** 97%+ line coverage (EXCELLENT quality)

**Test Suite Stats:**
- Total: 23 tests
- Unit: 13 tests
- Integration: 10 tests

---

## Common Issues & Solutions

### Issue: "composer: command not found"

**Problem:** You're on the Silverblue host, not in toolbox

**Solution:**
```bash
# Use the helper script
./run-tests.sh

# OR manually enter toolbox
toolbox enter inat-observations
```

---

### Issue: "php: No such file or directory"

**Problem:** PHP not installed in toolbox

**Solution:**
```bash
# Inside toolbox
sudo dnf install -y php php-cli php-json php-xml php-mbstring composer
```

---

### Issue: "WordPress test library not found"

**Problem:** Integration tests need WordPress test environment

**Solution:**

**Option 1: Run unit tests only (recommended for now)**
```bash
TEST_TYPE=unit ./vendor/bin/phpunit tests/unit/
```

**Option 2: Install WordPress test library (advanced)**
```bash
# Install prerequisites
sudo dnf install -y mysql git

# Download install script
curl -O https://raw.githubusercontent.com/wp-cli/scaffold-command/master/templates/install-wp-tests.sh
chmod +x install-wp-tests.sh

# Install test library
bash install-wp-tests.sh wordpress_test root '' localhost latest
```

---

### Issue: "Cannot connect to database"

**Problem:** Integration tests need MySQL for database tests

**Solution:**
```bash
# Start Docker Compose stack (provides MySQL)
./inat.sh

# OR start MySQL directly
sudo dnf install -y mysql-server
sudo systemctl start mysqld
```

---

## Development Workflow

### TDD (Test-Driven Development)

```bash
# 1. Enter toolbox
toolbox enter inat-observations

# 2. Write a failing test
vim tests/unit/test-api.php

# 3. Run the test (should fail)
./vendor/bin/phpunit tests/unit/test-api.php

# 4. Write code to make it pass
vim wp-content/plugins/inat-observations-wp/includes/api.php

# 5. Run test again (should pass)
./vendor/bin/phpunit tests/unit/test-api.php

# 6. Refactor and repeat
```

---

### Coverage-Driven Development

```bash
# 1. Generate coverage report
composer test:coverage

# 2. Open in browser
open dashboard/coverage/index.html

# 3. Identify uncovered lines (red/yellow)

# 4. Write tests for uncovered code

# 5. Re-run coverage
composer test:coverage

# 6. Check improvement
```

---

## CI/CD Integration

Tests run automatically on GitHub Actions:

```yaml
# .github/workflows/tests.yml
- name: Run Tests
  run: |
    toolbox create test-container
    toolbox run -c test-container bash -c "
      sudo dnf install -y php composer
      composer install
      composer test
    "
```

---

## Next Steps

**To reach 97% coverage**, write tests for:

1. **Priority 1** (Week 1):
   - ✅ test-api.php (done)
   - ⏸ test-init.php (refresh job, hooks)
   - ⏸ Improve test-db-schema.php to 98%

2. **Priority 2** (Week 2):
   - ⏸ test-rest.php (REST API endpoint)
   - ⏸ test-shortcode.php (frontend rendering)
   - ⏸ test-admin.php (settings page)

3. **Priority 3** (Week 3):
   - ⏸ Edge cases and error paths
   - ⏸ Security tests
   - ⏸ test-uninstall.php

See `TODO-QA-002-test-coverage-goals.md` for detailed roadmap.

---

## Quick Reference

```bash
# Helper script (recommended)
./run-tests.sh

# Manual (inside toolbox)
composer test                           # All tests
composer test:coverage                  # With HTML coverage
composer lint                           # Code style check
composer lint:fix                       # Auto-fix code style
composer dashboard:build                # Full dashboard build

# PHPUnit directly
./vendor/bin/phpunit                    # All tests
./vendor/bin/phpunit tests/unit/        # Unit tests only
./vendor/bin/phpunit tests/integration/ # Integration tests only
./vendor/bin/phpunit --testdox          # Human-readable output
./vendor/bin/phpunit --filter test_fetch_observations_success  # Specific test
```

---

**Toolbox Commands:**
```bash
toolbox enter inat-observations         # Enter toolbox
toolbox list                            # List all toolboxes
toolbox rm inat-observations            # Remove toolbox (clean slate)
exit                                    # Exit toolbox
```

---

**Last Updated:** 2026-01-06
**Maintainer:** Ayahuitl Tlatoani
