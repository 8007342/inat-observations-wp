// Client-side enhancement for filters and list rendering.
(function(){
  console.log('[iNat] Script loaded');

  document.addEventListener('DOMContentLoaded', function(){
    console.log('[iNat] DOM ready');

    const root = document.getElementById('inat-observations-root');
    if(!root) {
      console.error('[iNat] Root element not found');
      return;
    }
    console.log('[iNat] Root element found');

    const listContainer = root.querySelector('#inat-list');
    const filterContainer = root.querySelector('#inat-filter-field');

    // Check for localized settings
    if(typeof inatObsSettings === 'undefined') {
      console.error('[iNat] inatObsSettings not found');
      listContainer.innerHTML = '<p style="color: red;">Configuration error: inatObsSettings not found</p>';
      return;
    }
    console.log('[iNat] Settings:', inatObsSettings);

    // View and filter state
    let currentView = 'grid';  // 'grid' or 'list'
    let currentPage = 1;
    let currentPerPage = inatObsSettings.perPage || 50;
    let totalResults = 0;
    let totalPages = 1;
    let currentSort = 'date';  // 'date', 'species', 'location', 'taxon'
    let currentOrder = 'desc';  // 'asc', 'desc'
    let currentFilters = {
      species: [],  // Array of species names (multi-select)
      location: [],  // Array of location names (multi-select)
      hasDNA: false
    };
    let textFiltersVisible = false;  // Collapsible text search filters

    // Autocomplete cache (loaded from server once, filtered client-side)
    let autocompleteCache = {
      species: null,
      location: null
    };

    // Function to fetch and render observations
    function fetchObservations() {
      console.log('[iNat] Fetching observations...', { page: currentPage, perPage: currentPerPage });

      // Build URL with nonce for CSRF protection
      const url = new URL(inatObsSettings.ajaxUrl);
      url.searchParams.set('action', 'inat_obs_fetch');
      url.searchParams.set('nonce', inatObsSettings.nonce);
      url.searchParams.set('per_page', currentPerPage);
      url.searchParams.set('page', currentPage);

      // Add filters if set (arrays sent as JSON)
      if (currentFilters.species.length > 0) {
        const speciesJson = JSON.stringify(currentFilters.species);
        url.searchParams.set('species', speciesJson);
        console.log('[iNat] Adding species filter to URL:', speciesJson);
      }
      if (currentFilters.location.length > 0) {
        const locationJson = JSON.stringify(currentFilters.location);
        url.searchParams.set('place', locationJson);
        console.log('[iNat] Adding location filter to URL:', locationJson);
      }
      if (currentFilters.hasDNA) {
        url.searchParams.set('has_dna', '1');
        console.log('[iNat] Adding DNA filter to URL');
      }

      // Add sort parameters
      url.searchParams.set('sort', currentSort);
      url.searchParams.set('order', currentOrder);
      console.log('[iNat] Adding sort params:', currentSort, currentOrder);

      console.log('[iNat] Final fetch URL:', url.toString());
      console.log('[iNat] Current filters state:', JSON.parse(JSON.stringify(currentFilters)));

      // Fetch observations via AJAX endpoint
      fetch(url)
        .then(r => {
          console.log('[iNat] Response status:', r.status);
          if (!r.ok) {
            throw new Error('HTTP ' + r.status);
          }
          return r.json();
        })
        .then(j => {
          console.log('[iNat] Response data:', j);
          if(!j.success) {
            console.error('[iNat] API error:', j.data?.message);
            listContainer.innerHTML = '<p style="color: red;">Error: ' + (j.data?.message || 'Failed to load observations') + '</p>';
            return;
          }
          const data = j.data;
          const results = data.results || [];
          totalResults = data.total || results.length;
          totalPages = data.total_pages || 1;
          console.log('[iNat] Got', results.length, 'results,', totalResults, 'total across', totalPages, 'pages');

          // Variable to store no results message (shown instead of grid/list)
          let noResultsMessage = null;

          // Check if empty
          if (results.length === 0) {
            // Determine if this is due to filters or empty database
            const hasFilters = currentFilters.species.length > 0 || currentFilters.location.length > 0 || currentFilters.hasDNA;

            if (hasFilters) {
              // Filtered query with no results - show graceful recovery WITH filter bar
              // Users can modify filters directly or click reset
              noResultsMessage = '<div style="padding: 40px 20px; text-align: center; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px; margin-top: 20px;">' +
                '<h3 style="margin: 0 0 10px 0; color: #666;">No observations match your filters</h3>' +
                '<p style="margin: 0 0 20px 0; color: #888;">Try different search terms or remove some filters.</p>' +
                '<button id="inat-reset-filters" style="padding: 10px 20px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Reset All Filters</button>' +
                '</div>';

              // Continue rendering to show filter bar (don't return early)
              totalResults = 0;
              totalPages = 0;
              // Will render filter bar below, then show no results message
            } else {
              // Empty database - show setup instructions
              listContainer.innerHTML = '<div style="padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">' +
                '<h3>No observations found</h3>' +
                '<p>It looks like the database is empty. Please:</p>' +
                '<ol>' +
                '<li>Go to <strong>Settings → iNat Observations</strong></li>' +
                '<li>Click the <strong>"Refresh Now"</strong> button</li>' +
                '<li>Wait for observations to be fetched from iNaturalist</li>' +
                '<li>Come back to this page and refresh</li>' +
                '</ol>' +
                '</div>';
              filterContainer.parentElement.innerHTML = '<div id="inat-controls"></div>';
              return;
            }
          }

          // Build pagination controls
          const startIndex = currentPerPage === 'all' ? 1 : (currentPage - 1) * parseInt(currentPerPage) + 1;
          const endIndex = currentPerPage === 'all' ? totalResults : Math.min(currentPage * parseInt(currentPerPage), totalResults);

          // Auto-show Advanced Search if either filter has values
          if (currentFilters.species.length > 0 || currentFilters.location.length > 0) {
            textFiltersVisible = true;
          }

          // Filter bar - Unified search with DNA checkbox (invisible frame for positioning, mobile-optimized)
          let filterHtml = '<div id="inat-filter-bar" style="margin-bottom: 15px; padding: 12px; border: 1px solid transparent; border-radius: 4px; background: transparent; max-width: 100%; overflow-x: auto; overflow-y: visible; position: relative; z-index: 100000; box-sizing: border-box;">';

          // Main filter row: DNA checkbox + unified search input
          filterHtml += '<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; max-width: 100%; position: relative;">';

          // DNA checkbox (compact - just emoji, no text for mobile)
          filterHtml += '<label style="display: flex; align-items: center; gap: 6px; font-size: 20px; cursor: pointer; padding: 6px 10px; background: white; border: 2px solid ' + (currentFilters.hasDNA ? '#2271b1' : '#ddd') + '; border-radius: 4px; transition: all 0.2s;">';
          filterHtml += '<input type="checkbox" id="inat-filter-dna" ' + (currentFilters.hasDNA ? 'checked' : '') + ' style="width: 18px; height: 18px; cursor: pointer;">';
          filterHtml += '<span style="line-height: 1;">🧬</span>';
          filterHtml += '</label>';

          // Unified search input (for both species and locations)
          filterHtml += '<div style="position: relative; flex: 1; min-width: 200px; max-width: 100%; overflow: visible; z-index: 100001;">';
          filterHtml += '<input type="text" id="inat-unified-search" placeholder="Search species or location..." style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
          filterHtml += '</div>';

          filterHtml += '</div>';

          // Chips row (only visible when filters are active)
          const hasActiveFilters = currentFilters.species.length > 0 || currentFilters.location.length > 0;
          if (hasActiveFilters) {
            filterHtml += '<div style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap; margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';

            // Species chips (blue with clipboard emoji)
            currentFilters.species.forEach(name => {
              filterHtml += '<span class="inat-chip" data-field="species" data-value="' + escapeHtml(name) + '" style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; background: #2271b1; color: white; border-radius: 16px; font-size: 13px; cursor: default;">';
              filterHtml += '📋 ' + escapeHtml(name);
              filterHtml += '<button class="inat-chip-remove" style="background: none; border: none; color: white; cursor: pointer; padding: 0; width: 18px; height: 18px; line-height: 1; font-size: 18px; margin-left: 2px;">×</button>';
              filterHtml += '</span>';
            });

            // Location chips (green with location emoji)
            currentFilters.location.forEach(name => {
              filterHtml += '<span class="inat-chip" data-field="location" data-value="' + escapeHtml(name) + '" style="display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; background: #2d7f3a; color: white; border-radius: 16px; font-size: 13px; cursor: default;">';
              filterHtml += '📍 ' + escapeHtml(name);
              filterHtml += '<button class="inat-chip-remove" style="background: none; border: none; color: white; cursor: pointer; padding: 0; width: 18px; height: 18px; line-height: 1; font-size: 18px; margin-left: 2px;">×</button>';
              filterHtml += '</span>';
            });

            filterHtml += '</div>';
          }

          filterHtml += '</div>';

          let controlsHtml = '<div id="inat-controls" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; max-width: 100%; overflow-x: auto; position: relative; z-index: 1;">';

          // View toggle
          controlsHtml += '<div style="display: flex; gap: 5px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
          controlsHtml += '<button id="inat-view-grid" style="padding: 5px 12px; border: none; background: ' + (currentView === 'grid' ? '#2271b1' : '#fff') + '; color: ' + (currentView === 'grid' ? '#fff' : '#333') + '; cursor: pointer;">🔲 Grid</button>';
          controlsHtml += '<button id="inat-view-list" style="padding: 5px 12px; border: none; background: ' + (currentView === 'list' ? '#2271b1' : '#fff') + '; color: ' + (currentView === 'list' ? '#fff' : '#333') + '; cursor: pointer;">☰ List</button>';
          controlsHtml += '</div>';

          // Per-page selector
          controlsHtml += '<div>';
          controlsHtml += '<label for="inat-per-page" style="margin-right: 5px;">Show:</label>';
          controlsHtml += '<select id="inat-per-page" style="padding: 5px;">';
          const perPageOptions = ['10', '50', '200', 'all'];
          perPageOptions.forEach(opt => {
            const selected = (opt === String(currentPerPage)) ? 'selected' : '';
            controlsHtml += '<option value="' + escapeHtml(opt) + '" ' + selected + '>' + (opt === 'all' ? 'All' : opt) + '</option>';
          });
          controlsHtml += '</select>';
          controlsHtml += '</div>';

          // Sort selector
          controlsHtml += '<div>';
          controlsHtml += '<label for="inat-sort" style="margin-right: 5px;">Sort by:</label>';
          controlsHtml += '<select id="inat-sort" style="padding: 5px;">';

          const sortOptions = [
            { value: 'date-desc', label: 'Date (Latest)', sort: 'date', order: 'desc' },
            { value: 'date-asc', label: 'Date (Oldest)', sort: 'date', order: 'asc' },
            { value: 'species-asc', label: 'Species (A-Z)', sort: 'species', order: 'asc' },
            { value: 'species-desc', label: 'Species (Z-A)', sort: 'species', order: 'desc' },
            { value: 'location-asc', label: 'Location (A-Z)', sort: 'location', order: 'asc' },
            { value: 'location-desc', label: 'Location (Z-A)', sort: 'location', order: 'desc' }
          ];

          const currentSortValue = currentSort + '-' + currentOrder;
          sortOptions.forEach(opt => {
            const selected = (opt.value === currentSortValue) ? 'selected' : '';
            controlsHtml += '<option value="' + escapeHtml(opt.value) + '" ' + selected + '>' + escapeHtml(opt.label) + '</option>';
          });

          controlsHtml += '</select>';
          controlsHtml += '</div>';

          // Pagination bar (only if not "all")
          if (currentPerPage !== 'all' && totalPages > 1) {
            controlsHtml += '<div style="display: flex; gap: 5px; align-items: center;">';

            const hasPrev = currentPage > 1;
            const hasNext = currentPage < totalPages;

            // Previous button
            if (hasPrev) {
              controlsHtml += '<button id="inat-prev" style="padding: 5px 10px; cursor: pointer; border: 1px solid #ddd; background: white; border-radius: 3px;">←</button>';
            } else {
              controlsHtml += '<button disabled style="padding: 5px 10px; cursor: not-allowed; border: 1px solid #ddd; background: #f5f5f5; color: #ccc; border-radius: 3px;">←</button>';
            }

            // Page numbers (responsive - show max 7 buttons on desktop, 5 on mobile)
            const maxButtons = window.innerWidth < 768 ? 5 : 7;
            const pageButtons = buildPageButtons(currentPage, totalPages, maxButtons);

            pageButtons.forEach(item => {
              if (item === '...') {
                controlsHtml += '<span style="padding: 5px 10px; color: #666;">...</span>';
              } else {
                const isActive = item === currentPage;
                controlsHtml += '<button class="inat-page-btn" data-page="' + item + '" style="padding: 5px 10px; cursor: pointer; border: 1px solid ' + (isActive ? '#2271b1' : '#ddd') + '; background: ' + (isActive ? '#2271b1' : 'white') + '; color: ' + (isActive ? 'white' : '#333') + '; border-radius: 3px; font-weight: ' + (isActive ? '600' : 'normal') + ';">' + item + '</button>';
              }
            });

            // Next button
            if (hasNext) {
              controlsHtml += '<button id="inat-next" style="padding: 5px 10px; cursor: pointer; border: 1px solid #ddd; background: white; border-radius: 3px;">→</button>';
            } else {
              controlsHtml += '<button disabled style="padding: 5px 10px; cursor: not-allowed; border: 1px solid #ddd; background: #f5f5f5; color: #ccc; border-radius: 3px;">→</button>';
            }

            controlsHtml += '</div>';
          }

          controlsHtml += '<div style="margin-left: auto; color: #666;">Showing ' + startIndex + '-' + endIndex + ' of ' + totalResults + '+ observations</div>';
          controlsHtml += '</div>';

          // Render observations (grid or list view)
          let html = '';
          let hasAnyPhotos = false;

          if (currentView === 'list') {
            // LIST VIEW - Table format
            html = renderListView(results);
            hasAnyPhotos = results.some(obs => obs.photo_url);
          } else {
            // GRID VIEW - Card format
            html = '<div class="inat-observations-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';

          results.forEach(obs => {
            const species = obs.species_guess || 'Unknown species';
            const taxonName = obs.taxon_name || '';  // Scientific name (binomial nomenclature)
            const place = obs.place_guess || 'Unknown location';
            // Remove time portion from date (only show YYYY-MM-DD)
            const dateRaw = obs.observed_on || 'Unknown date';
            const date = dateRaw.split(' ')[0] || dateRaw;  // Keep only date part
            const id = obs.id || '';

            // Photo data
            const photoUrl = obs.photo_url || '';
            const photoAttribution = obs.photo_attribution || 'iNaturalist User';
            const photoLicense = obs.photo_license || 'C';

            // License display mapping
            const licenseDisplay = {
              'cc-by': 'CC BY',
              'cc-by-nc': 'CC BY-NC',
              'cc-by-sa': 'CC BY-SA',
              'cc-by-nd': 'CC BY-ND',
              'cc0': 'CC0',
              'C': 'All Rights Reserved'
            }[photoLicense] || photoLicense;

            // Build URL (deep link on mobile, web on desktop)
            const inatUrl = id ? buildINatUrl(id) : '';

            // Card wrapper - clickable if we have an ID
            if (inatUrl) {
              html += '<a href="' + escapeHtml(inatUrl) + '" target="_blank" style="text-decoration: none; color: inherit; display: block;">';
            }

            html += '<div class="inat-observation-card" style="border: 1px solid #ddd; padding: 15px; background: #fff; transition: transform 0.2s, box-shadow 0.2s; cursor: ' + (inatUrl ? 'pointer' : 'default') + ';" onmouseover="if(this.style.cursor===\'pointer\') { this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 8px rgba(0,0,0,0.1)\'; }" onmouseout="this.style.transform=\'\'; this.style.boxShadow=\'\';">';

            // Thumbnail image (if available) - RESPONSIVE with srcset
            if (photoUrl) {
              hasAnyPhotos = true;
              const imgData = buildImageSrcset(photoUrl);
              html += '<div class="inat-card-image" style="margin-bottom: 10px; aspect-ratio: 4/3; overflow: hidden; background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);">';
              html += '<img src="' + escapeHtml(imgData.src) + '" ';
              html += 'srcset="' + escapeHtml(imgData.srcset) + '" ';
              html += 'sizes="(max-width: 480px) 240px, (max-width: 768px) 500px, 250px" ';  // Card is 250px wide
              html += 'alt="' + escapeHtml(species) + '" ';
              html += 'title="Photo © ' + escapeHtml(photoAttribution) + ' (' + escapeHtml(licenseDisplay) + ')" ';
              html += 'loading="lazy" ';
              html += 'style="width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s ease-in-out;" ';
              html += 'onerror="this.style.display=\'none\'; this.parentElement.style.background=\'#f5f5f5\';">';
              html += '</div>';
            }

            // Common name
            html += '<h4 style="margin: 0 0 5px 0; font-size: 16px;">' + escapeHtml(species) + '</h4>';

            // Scientific name (italic) - binomial nomenclature
            if (taxonName) {
              html += '<p style="margin: 0 0 10px 0; font-style: italic; color: #555; font-size: 13px;">' + escapeHtml(taxonName) + '</p>';
            }

            html += '<p style="margin: 5px 0; color: #666; font-size: 14px;">📍 ' + escapeHtml(place) + '</p>';
            html += '<p style="margin: 5px 0; color: #666; font-size: 14px;">📅 ' + escapeHtml(date) + '</p>';

            html += '</div>';

            if (inatUrl) {
              html += '</a>';  // Close clickable wrapper
            }
          });
          html += '</div>';
          }

          // Legal footer (if any photos present)
          if (hasAnyPhotos) {
            html += '<div class="inat-attribution" style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-top: 2px solid #e0e0e0; font-size: 13px; color: #666; text-align: center;">';
            html += 'Observation images © their respective photographers, licensed under ';
            html += '<a href="https://creativecommons.org/licenses/" target="_blank" rel="noopener">Creative Commons</a> or All Rights Reserved. ';
            html += 'Hover over images for attribution. Hosted by <a href="https://www.inaturalist.org" target="_blank" rel="noopener">iNaturalist</a>.';
            html += '</div>';
          }

          // Render: filterHtml + controlsHtml + (noResultsMessage OR html)
          listContainer.innerHTML = filterHtml + controlsHtml + (noResultsMessage || html);

          // Attach event handlers
          const perPageSelect = document.getElementById('inat-per-page');
          if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
              currentPerPage = this.value;
              currentPage = 1;
              fetchObservations();
            });
          }

          const sortSelect = document.getElementById('inat-sort');
          if (sortSelect) {
            sortSelect.addEventListener('change', function() {
              const parts = this.value.split('-');
              if (parts.length === 2) {
                currentSort = parts[0];
                currentOrder = parts[1];
                currentPage = 1;
                console.log('[iNat] Sort changed via dropdown:', currentSort, currentOrder);
                fetchObservations();
              }
            });
          }

          const prevBtn = document.getElementById('inat-prev');
          if (prevBtn) {
            prevBtn.addEventListener('click', function() {
              if (currentPage > 1) {
                currentPage--;
                fetchObservations();
              }
            });
          }

          const nextBtn = document.getElementById('inat-next');
          if (nextBtn) {
            nextBtn.addEventListener('click', function() {
              if (currentPage < totalPages) {
                currentPage++;
                fetchObservations();
              }
            });
          }

          // Page number buttons
          const pageButtons = document.querySelectorAll('.inat-page-btn');
          pageButtons.forEach(btn => {
            btn.addEventListener('click', function() {
              const page = parseInt(this.getAttribute('data-page'));
              if (page && page !== currentPage) {
                currentPage = page;
                fetchObservations();
              }
            });
          });

          // View toggle buttons
          const gridBtn = document.getElementById('inat-view-grid');
          if (gridBtn) {
            gridBtn.addEventListener('click', function() {
              currentView = 'grid';
              fetchObservations();
            });
          }

          const listBtn = document.getElementById('inat-view-list');
          if (listBtn) {
            listBtn.addEventListener('click', function() {
              currentView = 'list';
              fetchObservations();
            });
          }

          // Sortable column headers (LIST VIEW only)
          const sortableHeaders = document.querySelectorAll('th[data-sort]');
          sortableHeaders.forEach(header => {
            header.addEventListener('click', function() {
              const column = this.getAttribute('data-sort');
              if (column) {
                handleSortClick(column);
              }
            });

            // Add hover effect
            header.addEventListener('mouseenter', function() {
              if (currentSort !== this.getAttribute('data-sort')) {
                this.style.background = '#f0f0f0';
              }
            });
            header.addEventListener('mouseleave', function() {
              this.style.background = '';
            });
          });

          // DNA checkbox (THE STAR! 🧬)
          const dnaCheckbox = document.getElementById('inat-filter-dna');
          if (dnaCheckbox) {
            dnaCheckbox.addEventListener('change', function() {
              currentFilters.hasDNA = this.checked;
              currentPage = 1;  // Reset to first page when filtering
              fetchObservations();
            });
          }

          // Reset All Filters button (shown in no results message)
          const resetFiltersBtn = document.getElementById('inat-reset-filters');
          if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', function() {
              currentFilters.species = [];
              currentFilters.location = [];
              currentFilters.hasDNA = false;
              currentPage = 1;
              fetchObservations();
            });
          }

          // Chip remove buttons
          const chipRemoveButtons = document.querySelectorAll('.inat-chip-remove');
          chipRemoveButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              const chip = this.closest('.inat-chip');
              const field = chip.getAttribute('data-field');
              const value = chip.getAttribute('data-value');

              // Remove value from filter array
              if (field === 'species') {
                currentFilters.species = currentFilters.species.filter(v => v !== value);
              } else if (field === 'location') {
                currentFilters.location = currentFilters.location.filter(v => v !== value);
              }

              currentPage = 1;
              fetchObservations();  // Auto-reload on chip remove
            });
          });

          // TODO-BUG-002: Unified search autocomplete removed for clean reimplementation
          // Will be rewired with proper value normalization
          const unifiedSearch = document.getElementById('inat-unified-search');
          if (unifiedSearch) {
            // Placeholder: Input exists but no autocomplete attached yet
            console.log('[iNat] Unified search input found, awaiting reimplementation');
          }
        })
        .catch(e => {
          console.error('iNat observations fetch error:', e);

          // Graceful error recovery - show filters + reset button
          const hasFilters = currentFilters.species.length > 0 || currentFilters.location.length > 0 || currentFilters.hasDNA;

          let errorHtml = '<div style="padding: 40px 20px; text-align: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">';
          errorHtml += '<h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Something went wrong</h3>';
          errorHtml += '<p style="margin: 0 0 10px 0; color: #856404; font-size: 14px;">Unable to load observations. This might be a temporary issue.</p>';

          if (hasFilters) {
            errorHtml += '<p style="margin: 0 0 20px 0; color: #856404; font-size: 13px;">Try resetting your filters or refreshing the page.</p>';
            errorHtml += '<button id="inat-reset-filters" style="padding: 10px 20px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; margin-right: 10px;">Reset Filters</button>';
          } else {
            errorHtml += '<p style="margin: 0 0 20px 0; color: #856404; font-size: 13px;">Try refreshing the page.</p>';
          }

          errorHtml += '<button onclick="location.reload()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Refresh Page</button>';
          errorHtml += '<details style="margin-top: 20px; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">';
          errorHtml += '<summary style="cursor: pointer; color: #856404; font-size: 12px;">Technical details</summary>';
          errorHtml += '<pre style="margin-top: 10px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size: 11px; overflow-x: auto;">' + escapeHtml(e.message + '\n' + (e.stack || '')) + '</pre>';
          errorHtml += '</details>';
          errorHtml += '</div>';

          listContainer.innerHTML = errorHtml;

          // Attach reset button handler if filters are active
          if (hasFilters) {
            setTimeout(() => {
              const resetBtn = document.getElementById('inat-reset-filters');
              if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                  currentFilters.species = [];
                  currentFilters.location = [];
                  currentFilters.hasDNA = false;
                  currentPage = 1;
                  fetchObservations();
                });
              }
            }, 0);
          }
        });
    }

    // Load autocomplete suggestions (cached on server)
    function loadAutocomplete(field) {
      const url = new URL(inatObsSettings.ajaxUrl);
      url.searchParams.set('action', 'inat_obs_autocomplete');
      url.searchParams.set('nonce', inatObsSettings.nonce);
      url.searchParams.set('field', field);

      fetch(url)
        .then(r => r.json())
        .then(j => {
          if (j.success) {
            autocompleteCache[field] = j.data.suggestions;
            console.log('[iNat] Loaded ' + field + ' autocomplete: ' + autocompleteCache[field].length + ' items');
          }
        })
        .catch(e => {
          console.error('[iNat] Autocomplete fetch failed:', e);
        });
    }

    // Get sort arrow indicator
    function getSortArrow(column) {
      if (currentSort === column) {
        return currentOrder === 'asc' ? ' ↑' : ' ↓';
      }
      return ' ↕';  // Inactive column shows both arrows
    }

    // Handle sort column click
    function handleSortClick(column) {
      if (column === currentSort) {
        // Toggle order if same column
        currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
      } else {
        // Switch to new column, default to ascending (except date defaults to descending)
        currentSort = column;
        currentOrder = column === 'date' ? 'desc' : 'asc';
      }

      console.log('[iNat] Sort changed:', currentSort, currentOrder);

      // Reset to page 1 when sorting changes
      currentPage = 1;

      // Fetch with new sort
      fetchObservations();
    }

    // Render LIST VIEW - Table format
    function renderListView(results) {
      let html = '<div class="inat-list-view" style="overflow-x: auto;">';
      html += '<table style="width: 100%; border-collapse: collapse; background: #fff;">';

      // Table header with sortable columns
      html += '<thead>';
      html += '<tr style="background: #f9f9f9; border-bottom: 2px solid #ddd;">';

      // Photo column (not sortable)
      html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Photo</th>';

      // Species column (sortable)
      const speciesActive = currentSort === 'species';
      html += '<th data-sort="species" style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; cursor: pointer; user-select: none; ' + (speciesActive ? 'color: #2271b1;' : '') + '" title="Click to sort by species">';
      html += 'Species' + getSortArrow('species');
      html += '</th>';

      // Location column (sortable)
      const locationActive = currentSort === 'location';
      html += '<th data-sort="location" style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; cursor: pointer; user-select: none; ' + (locationActive ? 'color: #2271b1;' : '') + '" title="Click to sort by location">';
      html += 'Location' + getSortArrow('location');
      html += '</th>';

      // Date column (sortable)
      const dateActive = currentSort === 'date';
      html += '<th data-sort="date" style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600; cursor: pointer; user-select: none; ' + (dateActive ? 'color: #2271b1;' : '') + '" title="Click to sort by date">';
      html += 'Date' + getSortArrow('date');
      html += '</th>';

      // Actions column (not sortable)
      html += '<th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd; font-weight: 600;">Actions</th>';

      html += '</tr>';
      html += '</thead>';

      // Table body
      html += '<tbody>';
      results.forEach((obs, index) => {
        const species = obs.species_guess || 'Unknown species';
        const taxonName = obs.taxon_name || '';  // Scientific name (binomial nomenclature)
        const place = obs.place_guess || 'Unknown location';
        // Remove time portion from date (only show YYYY-MM-DD)
        const dateRaw = obs.observed_on || 'Unknown date';
        const date = dateRaw.split(' ')[0] || dateRaw;  // Keep only date part
        const id = obs.id || '';
        const photoUrl = obs.photo_url || '';
        const photoAttribution = obs.photo_attribution || 'iNaturalist User';
        const photoLicense = obs.photo_license || 'C';

        const licenseDisplay = {
          'cc-by': 'CC BY',
          'cc-by-nc': 'CC BY-NC',
          'cc-by-sa': 'CC BY-SA',
          'cc-by-nd': 'CC BY-ND',
          'cc0': 'CC0',
          'C': 'All Rights Reserved'
        }[photoLicense] || photoLicense;

        const rowStyle = index % 2 === 0 ? 'background: #fff;' : 'background: #f9f9f9;';
        const inatUrl = id ? buildINatUrl(id) : '';

        // Make row clickable if we have a URL
        const rowClickStyle = inatUrl ? ' cursor: pointer;' : '';
        const rowClickHandler = inatUrl ? ' onclick="window.open(\'' + escapeHtml(inatUrl) + '\', \'_blank\')"' : '';
        const rowHoverHandler = inatUrl ? ' onmouseover="this.style.backgroundColor=\'#e8f4f8\';" onmouseout="this.style.backgroundColor=\'' + (index % 2 === 0 ? '#fff' : '#f9f9f9') + '\';"' : '';

        html += '<tr style="' + rowStyle + ' border-bottom: 1px solid #ddd;' + rowClickStyle + '"' + rowClickHandler + rowHoverHandler + '>';

        // Photo thumbnail (small in list view)
        html += '<td style="padding: 8px;">';
        if (photoUrl) {
          const imgData = buildImageSrcset(photoUrl);
          html += '<img src="' + escapeHtml(imgData.src) + '" ';
          html += 'srcset="' + escapeHtml(imgData.srcset) + '" ';
          html += 'sizes="80px" ';  // Small thumbnail in list view
          html += 'alt="' + escapeHtml(species) + '" ';
          html += 'title="Photo © ' + escapeHtml(photoAttribution) + ' (' + escapeHtml(licenseDisplay) + ')" ';
          html += 'loading="lazy" ';
          html += 'style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;" ';
          html += 'onerror="this.style.display=\'none\';">';
        } else {
          html += '<div style="width: 80px; height: 60px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 11px;">No photo</div>';
        }
        html += '</td>';

        // Species (with scientific name)
        html += '<td style="padding: 8px;">';
        html += '<strong>' + escapeHtml(species) + '</strong>';
        if (taxonName) {
          html += '<br><span style="font-style: italic; color: #666; font-size: 12px;">' + escapeHtml(taxonName) + '</span>';
        }
        html += '</td>';

        // Location
        html += '<td style="padding: 8px;">' + escapeHtml(place) + '</td>';

        // Date
        html += '<td style="padding: 8px;">' + escapeHtml(date) + '</td>';

        // Actions (removed - row is now clickable)
        html += '<td style="padding: 8px; color: #999; font-size: 12px;">';
        if (inatUrl) {
          html += '↗';  // Arrow icon to indicate external link
        }
        html += '</td>';

        html += '</tr>';
      });
      html += '</tbody>';
      html += '</table>';
      html += '</div>';

      return html;
    }

    // Load autocomplete data on page load
    loadAutocomplete('species');
    loadAutocomplete('location');

    // Initial fetch
    console.log('[iNat] Starting initial fetch...');
    fetchObservations();

    // Hide the old filter container
    filterContainer.parentElement.style.display = 'none';
  });

  // Helper: Construct responsive image srcset from photo URL
  function buildImageSrcset(photoUrl) {
    if (!photoUrl) return { src: '', srcset: '', sizes: '' };

    // Extract base URL (remove filename, keep path)
    // Example: https://.../photos/12345/square.jpg → https://.../photos/12345/
    const base = photoUrl.substring(0, photoUrl.lastIndexOf('/') + 1);

    return {
      src: base + 'medium.jpg',  // Fallback for old browsers
      srcset: [
        base + 'small.jpg 240w',
        base + 'medium.jpg 500w',
        base + 'large.jpg 1024w',
        base + 'original.jpg 2048w'
      ].join(', '),
      sizes: '(max-width: 480px) 240px, (max-width: 768px) 500px, (max-width: 1200px) 1024px, 2048px'
    };
  }

  // Helper: Attach combined autocomplete (species + locations with emoji indicators)
  // TODO-BUG-002: attachCombinedAutocomplete removed for clean reimplementation
  // Will be rewritten with proper value normalization (UPPERCASE, accent removal, whitespace)

  // Helper: Attach autocomplete dropdown to an input
  function attachAutocomplete(input, field, cache, onSelectCallback) {
    // Create dropdown container
    const dropdown = document.createElement('div');
    dropdown.className = 'inat-autocomplete-dropdown';
    dropdown.style.cssText = `
      position: absolute;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      left: 0;
      right: 0;
    `;

    // Wrap input in a relative container if not already wrapped
    if (input.parentNode.style.position !== 'relative') {
      const wrapper = document.createElement('div');
      wrapper.style.position = 'relative';
      wrapper.style.display = 'inline-block';
      wrapper.style.width = '100%';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);
      wrapper.appendChild(dropdown);
    } else {
      input.parentNode.appendChild(dropdown);
    }

    // Handle input events
    input.addEventListener('input', function() {
      const query = this.value.toLowerCase().trim();

      if (!query || !cache[field]) {
        dropdown.style.display = 'none';
        return;
      }

      // Filter suggestions (client-side - FAST!)
      // Tlatoani's directive: Prefix matching only (uses brain index!)
      const matches = cache[field].filter(item =>
        item.toLowerCase().startsWith(query)
      ).slice(0, 10);  // Limit to 10 results

      if (matches.length === 0) {
        dropdown.style.display = 'none';
        return;
      }

      // Render dropdown
      dropdown.innerHTML = '';
      matches.forEach(item => {
        const option = document.createElement('div');
        option.textContent = item;
        option.style.cssText = `
          padding: 8px 12px;
          cursor: pointer;
          border-bottom: 1px solid #f0f0f0;
        `;
        option.addEventListener('mouseenter', function() {
          this.style.background = '#f0f0f0';
        });
        option.addEventListener('mouseleave', function() {
          this.style.background = 'white';
        });
        option.addEventListener('click', function() {
          dropdown.style.display = 'none';
          // Trigger callback if provided (passing selected value)
          if (onSelectCallback && typeof onSelectCallback === 'function') {
            onSelectCallback(item);
          }
        });
        dropdown.appendChild(option);
      });

      // Position dropdown below input
      dropdown.style.top = input.offsetHeight + 'px';
      dropdown.style.display = 'block';
    });

    // Hide dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (e.target !== input && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  // Helper: Build page button array with ellipsis
  // Returns array like: [1, '...', 5, 6, 7, 8, 9, '...', 20]
  function buildPageButtons(current, total, maxButtons) {
    if (total <= maxButtons) {
      // Show all pages if total is small
      const pages = [];
      for (let i = 1; i <= total; i++) {
        pages.push(i);
      }
      return pages;
    }

    const pages = [];
    const halfMax = Math.floor(maxButtons / 2);

    // Always show first page
    pages.push(1);

    // Calculate range around current page
    let start = Math.max(2, current - halfMax);
    let end = Math.min(total - 1, current + halfMax);

    // Adjust if at the beginning
    if (current <= halfMax + 1) {
      end = Math.min(total - 1, maxButtons - 1);
    }

    // Adjust if at the end
    if (current >= total - halfMax) {
      start = Math.max(2, total - maxButtons + 2);
    }

    // Add ellipsis if needed
    if (start > 2) {
      pages.push('...');
    }

    // Add middle pages
    for (let i = start; i <= end; i++) {
      pages.push(i);
    }

    // Add ellipsis if needed
    if (end < total - 1) {
      pages.push('...');
    }

    // Always show last page
    if (total > 1) {
      pages.push(total);
    }

    return pages;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Helper: Detect mobile device
  function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  // Helper: Build iNaturalist URL (deep link on mobile, web on desktop)
  function buildINatUrl(obsId) {
    if (isMobile()) {
      // Try deep link for mobile app (will fallback to web if app not installed)
      return 'inaturalist://observations/' + obsId;
    }
    return 'https://www.inaturalist.org/observations/' + obsId;
  }
})();
