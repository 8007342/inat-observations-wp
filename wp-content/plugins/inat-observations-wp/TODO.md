# TODO - nano-jira-lite

## High level
- [ ] Implement API fetch and pagination.
- [ ] Normalize observation field metadata.
- [ ] Create custom DB schema and migration.
- [ ] Build shortcode renderer with client-side filters.
- [ ] Add settings page for API token and project slug.
- [ ] Implement rate limiter and backoff.
- [ ] Unit tests and coding standards.

## Development tasks (first pass)
1. Start docker and WP dev environment.
2. Activate plugin stub.
3. Implement `includes/api.php::fetch_observations`.
4. Implement transient cache usage around API calls.
5. Create `wp_inat_observations` table migration in `db-schema.php`.
6. Implement `includes/shortcode.php` to render minimal HTML and enqueue `assets/js/main.js`.
7. Wire WP-Cron job `inat_refresh` to run daily.
8. Add `.env` example and sanitize README.

## Publishing
- [ ] Clean secret and dev-only commits.
- [ ] Create a public repo with cleaned commit history.
- [ ] Prepare `readme.txt` for WordPress.org and submit.

