# TODO-main.md - inat-observations-wp Project Status

**Last Updated:** 2026-01-01
**Version:** 0.1.0
**Status:** Early Development (Skeleton Implementation)

> **WARNING: EARLY DEVELOPMENT**
> This plugin is in its earliest stages. Core functionality is stubbed.
> **DO NOT USE IN PRODUCTION** unless you understand the risks and are prepared
> to complete the implementation yourself. This is experimental software.

---

## Current Project State Overview

The `inat-observations-wp` plugin has a functional skeleton with the following architecture:

- **Main plugin file:** Defines constants and loads init.php
- **Modular includes:** api.php, db-schema.php, shortcode.php, rest.php, admin.php
- **Frontend assets:** Minimal JS/CSS for client-side rendering
- **Dev environment:** Docker Compose setup with WordPress + MySQL
- **CI/CD:** Claude Code GitHub Actions for PR review and assistance

The plugin can be activated and will create database tables on activation, schedule a daily cron job, and register a shortcode. However, most functionality is stubbed with TODO comments.

---

## Completed Features

### Infrastructure
- [x] WordPress plugin skeleton with proper header and constants
- [x] Docker Compose dev environment (WordPress + MySQL)
- [x] Git repository with .gitignore for secrets and temp files
- [x] GitHub Actions workflows (Claude Code Review, Claude PR Assistant)
- [x] Activation/deactivation hooks registered
- [x] WP-Cron daily refresh event scheduled on activation

### Database
- [x] Custom table schema defined (`wp_inat_observations`)
- [x] dbDelta migration function for table creation
- [x] Basic store_items function using wpdb->replace

### API
- [x] Basic fetch_observations function with wp_remote_get
- [x] Transient caching for API responses (configurable lifetime)
- [x] Environment variable support (INAT_PROJECT_SLUG, INAT_API_TOKEN, CACHE_LIFETIME)
- [x] Authorization header support for API token

### Frontend
- [x] Shortcode registered `[inat_observations]`
- [x] Basic HTML container with filter dropdown placeholder
- [x] CSS and JS assets enqueued
- [x] AJAX endpoint registered for frontend fetch
- [x] CSRF protection via nonce verification
- [x] wp_localize_script for AJAX URL and security token

### REST API
- [x] REST route registered at `inat/v1/observations`
- [x] Basic GET endpoint returning raw API data
- [x] Input validation for per_page parameter

### Admin
- [x] Settings page registered under WP Settings menu
- [x] Uninstall.php scaffold

---

## Pending Items

### HIGH Priority

#### 1. Complete API Pagination and Full Fetch
**File:** `/wp-content/plugins/inat-observations-wp/includes/api.php`
**Current State:** Single page fetch only; `inat_obs_fetch_all()` is empty

#### 2. Implement WP-Cron Refresh Job
**File:** `/wp-content/plugins/inat-observations-wp/includes/init.php`
**Current State:** Hook registered but `inat_obs_refresh_job()` is empty

#### 3. Admin Settings Page Implementation
**File:** `/wp-content/plugins/inat-observations-wp/includes/admin.php`
**Current State:** Settings page registered but no form

#### 4. Observation Field Metadata Normalization
**File:** `/wp-content/plugins/inat-observations-wp/includes/db-schema.php`
**Current State:** `observation_field_values` stored as raw JSON

### MEDIUM Priority

#### 5. Frontend Filter Dropdowns
**File:** `/wp-content/plugins/inat-observations-wp/assets/js/main.js`

#### 6. Observation List Rendering
**File:** `/wp-content/plugins/inat-observations-wp/assets/js/main.js`

#### 7. REST API Enhancements
**File:** `/wp-content/plugins/inat-observations-wp/includes/rest.php`

#### 8. Rate Limiting for AJAX/REST Endpoints

### LOW Priority

#### 9. Uninstall Cleanup
#### 10. Unit Tests and Coding Standards
#### 11. Shortcode Attributes
#### 12. WordPress.org Preparation
#### 13. Performance Optimization

---

## Known Issues and Bugs

### Critical
- **No error handling in refresh job:** If API fetch fails, no notification to admin
- **No validation of API response structure:** Could crash on malformed data

### Moderate
- ~~**ajaxurl undefined on frontend:** Need to use wp_localize_script~~ **FIXED**
- **Hardcoded project slug fallback:** Default "project_slug_here" will cause API errors

### Minor
- **CSS is minimal:** No responsive design, accessibility considerations
- **No loading indicators:** User sees "Loading..." text with no spinner

---

## Recommended Implementation Order

1. ~~**Fix ajaxurl bug** (Quick win, enables frontend testing)~~ **DONE**
2. **Admin settings page** (Enables proper configuration)
3. **Complete API pagination** (Required for full data sync)
4. **Implement cron refresh** (Enables automated updates)
5. **Metadata normalization** (Enables filtering)
6. **Frontend filters and rendering** (User-facing value)
7. **REST API improvements** (API consumers)
8. **Testing and standards** (Quality assurance)
9. **WordPress.org preparation** (Distribution)

---

**Last Updated**: 2026-01-01
