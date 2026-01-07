# TODO-BUG-001: iNat Refresh Limited to 200 Observations

**Status**: In Progress
**Priority**: High
**Reported**: 2026-01-07
**Estimated Effort**: 30 minutes

## Problem

The "Refresh Now" button and automatic daily refresh only fetch **200 observations** instead of all available observations from the iNaturalist project/user.

**Root Cause**: `init.php:45` hardcodes pagination to a single page:
```php
$args = ['per_page' => 200, 'page' => 1];
```

iNaturalist API returns max 200 results per request, so large projects/users are truncated.

## Expected Behavior

- **Fetch ALL observations** from the configured project/user
- Implement pagination loop to fetch multiple pages
- Handle API rate limits gracefully
- Show total count in admin UI

## Current Code

**File**: `wp-content/plugins/inat-observations-wp/includes/init.php:33-69`

```php
function inat_obs_refresh_job() {
    // ... settings validation ...

    // PROBLEM: Only fetches page 1
    $args = ['per_page' => 200, 'page' => 1];
    if (!empty($user_id)) {
        $args['user_id'] = $user_id;
    }
    if (!empty($project_id)) {
        $args['project'] = $project_id;
    }

    // Single fetch - no pagination loop
    $data = inat_obs_fetch_observations($args);
    if (is_wp_error($data)) {
        error_log('iNat Observations: API fetch failed - ' . $data->get_error_message());
        return;
    }

    inat_obs_store_items($data);
    $count = count($data['results'] ?? []);
    // ...
}
```

## Solution

Implement pagination loop in `inat_obs_refresh_job()`:

```php
function inat_obs_refresh_job() {
    // ... settings validation ...

    $page = 1;
    $per_page = 200; // Max allowed by iNaturalist API
    $total_fetched = 0;

    do {
        // Build args for current page
        $args = ['per_page' => $per_page, 'page' => $page];
        if (!empty($user_id)) {
            $args['user_id'] = $user_id;
        }
        if (!empty($project_id)) {
            $args['project'] = $project_id;
        }

        // Fetch page
        $data = inat_obs_fetch_observations($args);
        if (is_wp_error($data)) {
            error_log('iNat Observations: API fetch failed on page ' . $page . ' - ' . $data->get_error_message());
            break;
        }

        // Store observations
        inat_obs_store_items($data);

        $results_count = count($data['results'] ?? []);
        $total_fetched += $results_count;

        // Check if there are more pages
        // iNaturalist API: if results < per_page, we're on the last page
        if ($results_count < $per_page) {
            break;
        }

        $page++;

        // Rate limiting: sleep 1 second between requests to be polite
        sleep(1);

    } while (true);

    // Log success with total count
    update_option('inat_obs_last_refresh', current_time('mysql'));
    update_option('inat_obs_last_refresh_count', $total_fetched);

    error_log("iNat Observations: Refresh completed - fetched $total_fetched observations across " . ($page) . " page(s)");
}
```

## Testing

1. Configure plugin with a project that has > 200 observations
2. Click "Refresh Now" in admin
3. Check database: `SELECT COUNT(*) FROM wp_inat_observations;`
4. Verify count matches iNaturalist project total (or close to it)
5. Check admin "Last Refresh" shows total count

## Edge Cases

- **Empty results**: Break loop immediately
- **API errors**: Log error and stop gracefully
- **Rate limiting**: Add sleep(1) between requests
- **Timeout**: WordPress default is 30s - might need to increase for large projects
- **Memory**: 200 items per batch should be safe

## Related Code

- `includes/api.php:inat_obs_fetch_observations()` - API fetch function
- `includes/db-schema.php:inat_obs_store_items()` - Database storage (uses REPLACE for upserts)
- `includes/admin.php:inat_obs_ajax_manual_refresh()` - AJAX handler

## Notes

- The database uses `REPLACE` (upsert), so fetching same observations multiple times is safe
- iNaturalist API has rate limits - be polite with sleep() between requests
- Consider adding progress indicator for large projects (future enhancement)
- Current caching is per-URL, so pagination won't conflict

## Commit Message Template

```
Fix refresh to fetch all observations with pagination

Problem: Only fetching first 200 observations from iNaturalist
Root cause: init.php hardcoded single page fetch

Solution:
- Implement pagination loop in inat_obs_refresh_job()
- Fetch pages until results < per_page
- Add 1s sleep between requests (rate limit politeness)
- Log total observations fetched

Now fetches ALL observations from configured project/user
```
