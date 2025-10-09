# inat-observations-wp (Plugin)

This README covers plugin specific developer notes.

## Purpose
Store parsed iNaturalist observations in a local schema. Provide shortcode `[inat_observations]` and REST endpoints under `/wp-json/inat/v1/observations`.

## Quick dev notes
- Configuration is read from `wp-content/plugins/inat-observations-wp/.env` (not committed).
- The plugin uses WordPress Transients for light caching and a custom table `wp_inat_observations` for structured storage.
- The `includes/` folder contains modular PHP files: init, api, db, shortcode, rest, admin.

## Plugin activation
On activation the plugin registers DB schema and schedules cron if enabled.

## TODOs
See `TODO.md` for prioritized tasks.
