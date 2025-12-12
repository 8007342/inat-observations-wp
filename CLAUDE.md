# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin that fetches observation data from the iNaturalist API, parses observation metadata fields, caches structured results, and exposes them via shortcodes and REST endpoints. The plugin is project-agnostic and can work with any iNaturalist project by changing configuration.

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

## Plugin Architecture

The plugin follows WordPress conventions with modular components:

**Main entry point:** `wp-content/plugins/inat-observations-wp/inat-observations-wp.php`
- Defines constants: `INAT_OBS_VERSION`, `INAT_OBS_PATH`, `INAT_OBS_URL`
- Loads all components via `includes/init.php`

**Core components** (in `wp-content/plugins/inat-observations-wp/includes/`):
- `init.php` - Loads all modules, registers activation/deactivation hooks, schedules WP-Cron jobs
- `api.php` - Fetches observations from iNaturalist API with transient caching
- `db-schema.php` - Manages `wp_inat_observations` table schema and storage
- `shortcode.php` - Implements `[inat_observations]` shortcode and AJAX endpoint
- `rest.php` - Provides REST endpoint at `/wp-json/inat/v1/observations`
- `admin.php` - Admin settings page (stub implementation)

**Data flow:**
1. API calls go through `inat_obs_fetch_observations()` which uses WordPress transients for caching
2. Results are stored in custom table `wp_inat_observations` via `inat_obs_store_items()`
3. Frontend uses shortcode or REST endpoint to display observations
4. WP-Cron job `inat_obs_refresh` scheduled daily to refresh data

**Database schema:**
- Table: `wp_inat_observations`
- Key fields: `id` (iNat observation ID), `uuid`, `observed_on`, `species_guess`, `place_guess`, `metadata` (JSON)
- Migration uses `dbDelta()` in `inat_obs_install_schema()`

## Configuration

Configuration is read from environment variables (typically in `.env` file, not committed):
- `INAT_PROJECT_SLUG` - iNaturalist project identifier
- `INAT_API_TOKEN` - Optional API token for higher rate limits
- `CACHE_LIFETIME` - Transient cache duration in seconds (default: 3600)

Note: Keep API tokens out of source control. The `.gitignore` excludes `.env` files.

## Development Notes

**Error logging:** The plugin uses extensive `error_log()` calls throughout. View logs with `docker logs -f wordpress`.

**Activation behavior:**
- Creates/updates `wp_inat_observations` table
- Schedules daily WP-Cron job `inat_obs_refresh`

**Deactivation behavior:**
- Clears scheduled cron jobs
- Does NOT drop database table (data persists)

**Shortcode usage:**
```
[inat_observations project="project-slug" per_page="50"]
```

**REST endpoint:**
```
GET /wp-json/inat/v1/observations
```

**AJAX endpoint:**
- Action: `inat_obs_fetch`
- Available to both logged-in and non-logged-in users

## Assets

Frontend assets in `wp-content/plugins/inat-observations-wp/assets/`:
- `css/main.css` - Styles for observation display
- `js/main.js` - Client-side filtering and enhancement

Assets are enqueued via shortcode rendering, versioned with `INAT_OBS_VERSION`.

## Current State & TODOs

The plugin is in early development (v0.1.0). See `wp-content/plugins/inat-observations-wp/TODO.md` for tracked tasks.

Key unimplemented features:
- Pagination for API fetches (multi-page fetching)
- Rate limiting and exponential backoff
- Observation field metadata normalization
- Settings page UI for API token/project configuration
- Client-side filtering implementation in `main.js`
- WP-Cron job implementation (currently a stub)
