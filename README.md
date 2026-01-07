# inat-observations-wp

**Purpose**
This project is a WordPress plugin skeleton named `inat-observations-wp`. It fetches observation data from the iNaturalist API, parses observation metadata fields, caches structured results, and exposes them with filterable UI elements on a WordPress site. The plugin is project-agnostic and can be used against any iNaturalist project by changing a configuration value.

**Key goals**
- Fetch observations from the iNaturalist public API.
- Parse `observation_field_values` and normalize metadata.
- Cache results to avoid hitting API rate limits.
- Provide filter dropdowns for parsed metadata.
- Offer a shortcode and a REST endpoint for flexible embedding.

**Contents**
- [docker-compose.yml](/docker-compose.yml) : Disposable WordPress + MySQL dev environment using Docker. This image
    mounts the latest WordPress and MySQL docker images and launches a local dev environment
    ready for development.
- [/wp-content/plugins/inat-observations-wp](/wp-content/plugins/inat-observations-wp) : The actual plugin contents to match Wordpress nesting but only the deepest directory [inat-observations-wp](/wp-content/plugins/inat-observations-wp) is necessary; notice how it's mapped to `/var/www/html/wp-content/plugins/inat-observations-wp` in [docker-compose.yml](/docker-compose.yml).
- `.gitignore` : Ignore transient /tmp files and .env files used by the docker images.

**Development workflow**

**Quick Start (Recommended)**:
```bash
./inat.sh                      # Start WordPress dev environment
./inat.sh --clean-and-install  # Fresh install with auto-configuration
./inat.sh logs                 # View container logs
```

The `inat.sh` script automatically:
- Detects Fedora Silverblue and enters toolbox if needed
- Uses podman-compose (recommended) or docker-compose
- Provides easy commands for clean installs and log viewing
- See script for full usage options

**Manual workflow**:
- Install Docker/Podman as appropriate
  - Fedora Silverblue: Use podman-compose (built-in)
  - Other systems: docker-compose or podman-compose
- From [VS Code](https://code.visualstudio.com/) launch the dev environment by opening
    [docker-compose.yml](/docker-compose.yml) and `compose up/restart`
- Visit [localhost:8080](http://localhost:8080) to finish WordPress install the first time
- Visit [/wp-admin](http://localhost:8080/wp-admin) for the WordPress dashboard
- Activate the plugin from [WP Admin/Plugins](http://localhost:8080/wp-admin/plugins.php)

**Container configuration**:
- **PHP**: Custom config at `docker/php.ini`
  - 512MB memory limit (default: 128MB)
  - 5 minute execution time (default: 30s)
  - Optimized for large dataset processing
- **MySQL**: Custom config at `docker/mysql.cnf`
  - 64MB max packet size (default: 16MB)
  - 256MB buffer pool (default: 128MB)
  - Optimized for bulk inserts
- **Note**: Recreate containers after config changes: `./inat.sh --clean && ./inat.sh`

**Debug containers `wordpress` and `mysql`**
- `./inat.sh logs` (recommended) - tail all logs
- `podman logs -f wordpress` - WordPress output only
- `podman logs -f mysql` - MySQL output only
- Plugin debug logs appear in WordPress container logs


**Security note**
Keep your iNaturalist API token out of source control. Use `.env` or WP options stored with appropriate capabilities.

**Where to start**
- See `wp-content/plugins/inat-observations-wp/TODO.md` for tracked tasks.
- Open `wp-content/plugins/inat-observations-wp/README_PLUGIN.md` for plugin-specific docs and developer notes.

**Whimsical signature**
```
— Chatty for the Tlatoāni
May your fungi photos always focus.
```