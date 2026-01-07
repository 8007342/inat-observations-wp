# TODO-002: Pagination Galore

**Status**: Mostly Complete (Testing Pending)
**Priority**: High
**Reported**: 2026-01-06
**Last Updated**: 2026-01-06 - Corrected pagination values based on iNaturalist API documentation

## Problem

Pagination needs to be consistent across three different contexts:
1. **API Fetching** - How many observations to fetch per request from iNaturalist
2. **Database Queries** - How many observations to return in AJAX/REST endpoints
3. **Display** - How many observations to show per page in the UI

Currently, these are hardcoded in various places, making them difficult to configure.

## Requirements

### Settings Page Additions

Add three new configurable options to the admin settings page:

1. **Refresh Rate**
   - Controls how often wp-cron fetches fresh data from iNaturalist
   - Options: EVERY 4 HOURS, DAILY, WEEKLY
   - Default: DAILY
   - Implementation: Update wp_schedule_event() interval

2. **API Fetch Pagination Size**
   - Controls how many observations to fetch during refresh (TOTAL cache size)
   - Options: 200, 1000, 2000, 5000
   - Default: 2000
   - Max allowed by iNaturalist: 200 per request (so we still paginate internally)
   - Rate limiting: 1 second between requests (60 req/min, staying within recommended limit)
   - Time estimates:
     - 200 observations = 1 API request (~1 second)
     - 1,000 observations = 5 API requests (~5 seconds)
     - 2,000 observations = 10 API requests (~10 seconds)
     - 5,000 observations = 25 API requests (~25 seconds)

3. **Display Pagination Size**
   - Controls default number of observations shown in shortcode view
   - Options: 10, 50, 200, "all"
   - Default: 50
   - Can be overridden by shortcode attribute
   - Should be selectable from filter bar in rendered view

### Code Changes Required

#### 1. Admin Settings (includes/admin.php)

- Add three new register_setting() calls
- Add three new add_settings_field() calls
- Add callback functions for each field (dropdowns)
- Add validation for refresh rate options
- Update settings save handler

#### 2. Refresh Job (includes/init.php)

- Change wp_schedule_event() to use custom interval based on setting
- Add custom cron schedule for "4 hours" interval
- Update pagination loop to respect API fetch size setting
- Still use 200 per request (iNat API max), but stop after reaching setting limit

#### 3. AJAX Endpoint (includes/shortcode.php)

- inat_obs_ajax_fetch() should respect display pagination size
- Add pagination controls (page parameter)
- Cache should consider page number

#### 4. REST Endpoint (includes/rest.php)

- inat_obs_rest_fetch() should respect display pagination size
- Add page parameter to endpoint
- Return total count for pagination UI

#### 5. Frontend JavaScript (assets/js/main.js)

- Add pagination controls to filter bar
- Add "Show per page" dropdown (10, 50, 200, all)
- Add previous/next buttons
- Update AJAX call to include page and per_page parameters
- Show "Showing X-Y of Z observations"

#### 6. Shortcode Attributes (includes/shortcode.php)

- Keep per_page attribute for backwards compatibility
- Use plugin setting as default if not specified
- Pass to JavaScript via wp_localize_script

## Implementation Plan

### Phase 1: Settings UI (30 min) ✅ COMPLETED
- [x] Add refresh_rate field to admin.php
- [x] Add api_fetch_size field to admin.php (updated with corrected values: 200, 1000, 2000, 5000)
- [x] Add display_page_size field to admin.php
- [x] Add validation logic
- [x] Test settings save/load

**Update (2026-01-06)**: API fetch size values corrected based on iNaturalist API documentation:
- Changed from [400, 2000, 10000] to [200, 1000, 2000, 5000]
- Values now clearly aligned with 200-per-request API limit
- Added time estimates to help users understand impact of each option

### Phase 2: Backend Integration (45 min) ✅ MOSTLY COMPLETED
- [x] Add custom cron interval for 4 hours (init.php:12-21)
- [x] Update wp_schedule_event() in init.php (init.php:34-54)
- [x] Update inat_obs_refresh_job() to respect api_fetch_size (init.php:64-134)
- [x] Update inat_obs_ajax_fetch() to respect display_page_size (implemented)
- [x] Update inat_obs_rest_fetch() to respect display_page_size (implemented)
- [x] Add pagination parameters (page, per_page) to endpoints (implemented)

### Phase 3: Frontend Pagination (60 min) ✅ COMPLETED
- [x] Add filter bar UI in main.js (main.js:67-99)
- [x] Add "Show per page" dropdown (main.js:69-79)
- [x] Add previous/next buttons (main.js:85-95)
- [x] Add page number display (main.js:83)
- [x] Update AJAX call to include pagination params (main.js:24-28)
- [x] Handle "all" option (fetch all, no pagination) (main.js:64-66, 82-96)

### Phase 4: Testing (45 min)
- [ ] Write unit tests for settings validation
- [ ] Write integration tests for pagination logic
- [ ] Test with small datasets (< 50 observations)
- [ ] Test with large datasets (> 1000 observations)
- [ ] Test "all" option behavior
- [ ] Verify cron schedule changes

## Edge Cases

- **Empty Results**: Show appropriate message, hide pagination controls
- **Single Page**: Hide pagination controls if all results fit on one page
- **"All" Option**: May be slow for large datasets - add loading indicator
- **API Fetch Size > Available**: Stop when iNaturalist returns < 200 results
- **Refresh Rate Change**: Clear old schedule, create new one
- **Invalid Settings**: Fall back to defaults

## Testing Scenarios

1. **Small Project (< 50 observations)**
   - Set display size to 10
   - Verify pagination shows 5 pages
   - Click through all pages

2. **Large Project (> 1000 observations)**
   - Set API fetch size to 400
   - Trigger refresh
   - Verify only 400 observations fetched (2 API requests)

3. **Refresh Rate**
   - Set to EVERY 4 HOURS
   - Check wp_next_scheduled('inat_obs_refresh')
   - Verify interval is 4 hours (14400 seconds)

4. **Display "All"**
   - Set display size to "all"
   - Verify all cached observations shown
   - Check performance with large datasets

## Current Code Locations

- Settings page: `includes/admin.php:109-248`
- Refresh job: `includes/init.php:33-94`
- AJAX endpoint: `includes/shortcode.php:45-118`
- REST endpoint: `includes/rest.php`
- Frontend: `assets/js/main.js`

## Related TODOs

- TODO-BUG-001: Pagination loop (COMPLETED)
- TODO-003: inat.sh improvements (IN PROGRESS)

## Notes

- Keep pagination simple for MVP - just prev/next buttons
- Advanced features (jump to page, items per page in URL) can come later
- Performance: Caching already implemented, pagination just adds LIMIT/OFFSET
- Security: Validate page and per_page parameters (sanitize, bounds check)
