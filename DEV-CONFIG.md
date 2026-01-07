# Development Configuration

This document describes how to configure the plugin for development and testing.

---

## Cache TTL Configuration

By default, the plugin uses these cache durations:
- **Autocomplete data**: 1 hour (3600 seconds)
- **Filtered observations**: 5 minutes (300 seconds)
- **Unfiltered observations**: 1 hour (3600 seconds)

### Enable Development Mode (30-second cache)

For manual testing and cache behavior verification, you can reduce the cache TTL to 30 seconds.

**Method 1: Add to `wp-config.php` (recommended)**

Add this line before the `/* That's all, stop editing! */` comment:

```php
// Development mode: 30-second cache for manual testing
define('INAT_OBS_DEV_CACHE_TTL', 30);
```

**Method 2: Environment variable**

Set the constant via Docker Compose:

```yaml
services:
  wordpress:
    environment:
      INAT_OBS_DEV_CACHE_TTL: 30
```

**Method 3: PHP code**

For integration tests, set the constant in your test bootstrap:

```php
// tests/bootstrap.php
define('INAT_OBS_DEV_CACHE_TTL', 3);  // 3 seconds for fast tests
```

### Verify Cache TTL

After enabling development mode, check the behavior:

1. **Load the page** - First request will query the database
2. **Refresh immediately** - Should serve from cache (fast)
3. **Wait 30 seconds and refresh** - Should query database again (slow)

Watch the browser console for cache hit/miss messages.

---

## Integration Test Configuration

For integration tests, use even shorter cache durations:

```php
// tests/bootstrap.php
define('INAT_OBS_TEST_CACHE_TTL', 3);  // 3 seconds for testing cache expiration
```

This allows tests to verify:
- Cache hits (repeat request within 3 seconds)
- Cache misses (wait > 3 seconds)
- Cache invalidation

---

## Cache Locations

The following files respect the `INAT_OBS_DEV_CACHE_TTL` constant:

1. **`includes/rest.php`** - REST API observations endpoint
2. **`includes/shortcode.php`** - AJAX observations fetch
3. **`includes/autocomplete.php`** - Species and location autocomplete

---

## Disabling Cache (Not Recommended)

To completely disable caching (for debugging only):

```php
define('INAT_OBS_DEV_CACHE_TTL', 0);  // No caching
```

⚠️ **Warning**: This will cause every request to hit the database, which is slow and not representative of production behavior.

---

## Production Checklist

Before deploying to production, ensure:

- [ ] Remove or comment out `INAT_OBS_DEV_CACHE_TTL` from `wp-config.php`
- [ ] Verify cache behavior with default TTL values
- [ ] Test with realistic data volumes (1000+ observations)
- [ ] Check server logs for excessive database queries

---

## Related Files

- `includes/rest.php` - REST API with cache configuration
- `includes/shortcode.php` - AJAX handler with cache configuration
- `includes/autocomplete.php` - Autocomplete cache configuration
- `TODO-QA-001-comprehensive-integration-tests.md` - Test cache behavior
