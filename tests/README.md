# inat-observations-wp Test Suite

This directory contains the PHPUnit test suite for the inat-observations-wp WordPress plugin.

## Directory Structure

```
tests/
├── bootstrap.php                    # PHPUnit bootstrap file
├── phpunit.xml                      # PHPUnit configuration
├── unit/                            # Unit tests
│   └── test-db-schema.php          # Database schema tests
├── integration/                     # Integration tests
│   └── test-activation.php         # Plugin activation/deactivation tests
└── fixtures/                        # Test data and fixtures
```

## Prerequisites

1. Install Composer dependencies:
   ```bash
   composer install
   ```

2. Set up WordPress test environment:
   ```bash
   # Download and install WordPress test library
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

   Note: You'll need to create the `bin/install-wp-tests.sh` script or set up the WordPress test environment manually.

## Running Tests

### Run all tests:
```bash
composer test
```

Or directly with PHPUnit:
```bash
vendor/bin/phpunit
```

### Run specific test suites:

**Unit tests only:**
```bash
vendor/bin/phpunit --testsuite Unit
```

**Integration tests only:**
```bash
vendor/bin/phpunit --testsuite Integration
```

### Run specific test files:
```bash
vendor/bin/phpunit tests/unit/test-db-schema.php
vendor/bin/phpunit tests/integration/test-activation.php
```

### Run with code coverage:
```bash
composer test:coverage
```

This generates an HTML coverage report in the `coverage/` directory.

## Test Categories

### Unit Tests (`tests/unit/`)
- Test individual functions and methods in isolation
- Mock external dependencies
- Fast execution
- Current tests:
  - `test-db-schema.php`: Database schema and data storage tests

### Integration Tests (`tests/integration/`)
- Test interactions between components
- Test WordPress integration
- May be slower than unit tests
- Current tests:
  - `test-activation.php`: Plugin activation/deactivation workflow tests

## Writing Tests

### Unit Test Example:
```php
<?php
class Test_My_Feature extends WP_UnitTestCase {
    public function test_something() {
        $result = my_function();
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Test Example:
```php
<?php
class Test_My_Integration extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        // Setup code
    }

    public function test_full_workflow() {
        // Test complete workflow
    }
}
```

## Test Coverage Goals

- **Minimum overall:** 70%
- **Critical files:** 90%
  - `includes/db-schema.php`
  - `includes/api.php`
- **Admin UI:** 50%

## Continuous Integration

Tests run automatically on:
- Push to main/develop branches
- Pull requests to main branch

See `.github/workflows/tests.yml` for CI configuration.

## Troubleshooting

### WordPress test library not found
If you get "Could not find /tmp/wordpress-tests-lib/includes/functions.php", you need to install the WordPress test library. See Prerequisites section above.

### Database connection errors
Ensure your database credentials in the test environment match your local setup.

### Missing dependencies
Run `composer install` to install all required dependencies.
