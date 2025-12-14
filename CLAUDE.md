# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin (`inat-observations-wp`) that fetches observation data from the iNaturalist API, parses observation metadata fields, caches structured results, and exposes them via shortcodes and REST endpoints. The plugin is project-agnostic and can work with any iNaturalist project by changing configuration.

## Development Environment

**Start the development environment:**
```bash
docker compose up -d
```

**View logs:**
```bash
docker logs -f wordpress    # Plugin and WordPress logs
docker logs -f mysql        # Database logs
```

**Access points:**
- WordPress site: http://localhost:8080
- WordPress admin: http://localhost:8080/wp-admin
- Plugin activation: http://localhost:8080/wp-admin/plugins.php

**Stop environment:**
```bash
docker compose down
```

## Configuration

Configuration is read from environment variables (typically in `.env` file, not committed):
- `INAT_PROJECT_SLUG` - iNaturalist project identifier
- `INAT_API_TOKEN` - Optional API token for higher rate limits
- `CACHE_LIFETIME` - Transient cache duration in seconds (default: 3600)

Keep API tokens out of source control. The `.gitignore` excludes `.env` files.

## Architecture

### Plugin Structure

The plugin follows a modular architecture with separate concerns:

```
wp-content/plugins/inat-observations-wp/
├── inat-observations-wp.php   # Main plugin file, defines constants, loads init
├── includes/
│   ├── init.php               # Activation/deactivation hooks, cron scheduling
│   ├── api.php                # iNaturalist API client with transient caching
│   ├── db-schema.php          # Custom table schema and data storage
│   ├── shortcode.php          # [inat_observations] shortcode + AJAX endpoint
│   ├── rest.php               # REST API endpoint at /wp-json/inat/v1/observations
│   └── admin.php              # WP Admin settings page (stub)
├── assets/
│   ├── css/main.css
│   └── js/main.js
└── uninstall.php              # Cleanup on plugin deletion
```

### Key Constants

Defined in `inat-observations-wp.php`:
- `INAT_OBS_VERSION`: Plugin version (0.1.0)
- `INAT_OBS_PATH`: Absolute filesystem path to plugin directory
- `INAT_OBS_URL`: URL to plugin directory

### Data Flow

1. **API Fetch** (`api.php`):
   - Fetches observations from `https://api.inaturalist.org/v1/observations`
   - Uses WordPress transients for short-term caching (keyed by URL hash)
   - Supports optional bearer token authentication
   - Returns decoded JSON or `WP_Error`

2. **Storage** (`db-schema.php`):
   - Custom table: `wp_inat_observations` with columns: id, uuid, observed_on, species_guess, place_guess, metadata (JSON), created_at, updated_at
   - `inat_obs_store_items()` processes API results and stores in DB
   - Uses `$wpdb->replace()` for upserts
   - `observation_field_values` from API stored as JSON in `metadata` column
   - Migration uses `dbDelta()` in `inat_obs_install_schema()`

3. **Display**:
   - **Shortcode** (`shortcode.php`): `[inat_observations]` renders container div, enqueues JS/CSS, client-side JS calls AJAX endpoint
   - **REST API** (`rest.php`): GET `/wp-json/inat/v1/observations` returns observations (publicly accessible)
   - **AJAX** (`shortcode.php`): `wp_ajax_inat_obs_fetch` for client-side data fetching (available to both logged-in and non-logged-in users)

4. **Scheduled Refresh** (`init.php`):
   - Cron job `inat_obs_refresh` scheduled daily on plugin activation
   - Hook: `add_action('inat_obs_refresh', 'inat_obs_refresh_job')`
   - Implementation pending in `inat_obs_refresh_job()`

## Development Workflow

1. Make changes to plugin files in `wp-content/plugins/inat-observations-wp/`
2. Changes are immediately reflected due to volume mount in `docker-compose.yml`
3. Debug output appears in `docker logs -f wordpress` (plugin uses extensive `error_log()` calls)
4. Database is persisted in `./tmp/db`, WordPress files in `./tmp/html`

### Plugin Activation Flow

On activation (`register_activation_hook`):
- Creates/updates `wp_inat_observations` table using `dbDelta()`
- Schedules daily WP-Cron job `inat_obs_refresh` (if not already scheduled)

On deactivation (`register_deactivation_hook`):
- Clears `inat_obs_refresh` scheduled hook
- Does NOT drop database table (data persists)

## Usage Examples

**Shortcode:**
```
[inat_observations project="project-slug" per_page="50"]
```

**REST endpoint:**
```
GET /wp-json/inat/v1/observations
```

**AJAX endpoint:**
```
Action: inat_obs_fetch
```

## Assets

Frontend assets in `wp-content/plugins/inat-observations-wp/assets/`:
- `css/main.css` - Styles for observation display
- `js/main.js` - Client-side filtering and enhancement

Assets are enqueued via shortcode rendering, versioned with `INAT_OBS_VERSION`.

## Current State & TODOs

The plugin is in early development (v0.1.0). See `wp-content/plugins/inat-observations-wp/TODO.md` for tracked tasks.

Key unimplemented features:
- Pagination for API fetches (see `inat_obs_fetch_all()` stub)
- Rate limiting and exponential backoff
- Observation field metadata normalization
- Settings page UI for API token/project configuration
- Client-side filtering implementation in `main.js`
- WP-Cron job implementation (currently a stub)
- AJAX endpoint rate limiting
- REST endpoint filters and DB-backed results
- Secondary tables for observation fields normalization

## Docker Environment Details

- WordPress container: `wordpress:latest` on port 8080
- MySQL container: `mysql:latest`
- Database credentials: wordpress/wordpress/wordpress (user/password/database)
- Root password: root
- Plugin directory mounted directly to `/var/www/html/wp-content/plugins/inat-observations-wp`
- WordPress files stored in `./tmp/html` (gitignored)
- MySQL data stored in `./tmp/db` (gitignored)
