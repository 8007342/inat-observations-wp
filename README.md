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
- `docker-compose.yml` : disposable WordPress + MySQL dev environment using Docker.
- `run.sh` : bring up the environment.
- `clean.sh` : tear down and remove persistent state for a clean start.
- `wp-content/plugins/inat-observations-wp/` : plugin skeleton.
- `.gitignore` : sensitive files and transient outputs.

**Development workflow**
1. Install Docker and Docker Compose on your WSL environment.
2. From the project root run `./run.sh`.
3. Visit `http://localhost:8080` and finish WordPress install.
4. Copy the plugin folder into `wp-content/plugins/` (this repo already mounts it).
5. Activate plugin from WP Admin -> Plugins.
6. Configure API token via a `.env` file or in plugin settings once implemented.

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
"# inat-observations-wp" 
"# inat-observations-wp" 
