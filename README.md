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
- Install Docker as appropriate.
- From [VS Code](https://code.visualstudio.com/) launch the dev environment by opening the
    file [docker-compose.yml](/docker-compose.yml) and `compose up/restart` as appropriate.
- Visit [localhost:8080](http://localhost:8080) to finish WordPress install the first time; and
    [/wp-admin](http://localhost:8080/wp-admin) afterwards for the Wordpress dashboard.
- Activate the plugin from [WP Admin/Plugins](http://localhost:8080/wp-admin/plugins.php).

**Debug docker instances `wordpress` and `mysql`**
- `docker logs -f wordpress` on a terminal for WordPress output. Debug statements from the
        plugin development should show up here.
- `docker logs -f mysql` on a terminal for mysql output. Database logs are not controled by Wordpress.


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