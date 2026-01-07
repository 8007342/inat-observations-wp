// Client-side enhancement for filters and list rendering.
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const root = document.getElementById('inat-observations-root');
    if(!root) return;

    const listContainer = root.querySelector('#inat-list');
    const filterContainer = root.querySelector('#inat-filter-field');

    // Check for localized settings
    if(typeof inatObsSettings === 'undefined') {
      listContainer.innerHTML = '<p style="color: red;">Configuration error: inatObsSettings not found</p>';
      return;
    }

    // Pagination state
    let currentPage = 1;
    let currentPerPage = inatObsSettings.perPage || 50;
    let totalResults = 0;

    // Function to fetch and render observations
    function fetchObservations() {
      // Build URL with nonce for CSRF protection
      const url = new URL(inatObsSettings.ajaxUrl);
      url.searchParams.set('action', 'inat_obs_fetch');
      url.searchParams.set('nonce', inatObsSettings.nonce);
      url.searchParams.set('per_page', currentPerPage);
      url.searchParams.set('page', currentPage);

      // Fetch observations via AJAX endpoint
      fetch(url)
        .then(r => {
          if (!r.ok) {
            throw new Error('HTTP ' + r.status);
          }
          return r.json();
        })
        .then(j => {
          if(!j.success) {
            listContainer.innerHTML = '<p style="color: red;">Error: ' + (j.data?.message || 'Failed to load observations') + '</p>';
            return;
          }
          const data = j.data;
          const results = data.results || [];
          totalResults = results.length;

          // Check if empty
          if (results.length === 0) {
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

          // Build pagination controls
          const startIndex = currentPerPage === 'all' ? 1 : (currentPage - 1) * parseInt(currentPerPage) + 1;
          const endIndex = currentPerPage === 'all' ? totalResults : Math.min(currentPage * parseInt(currentPerPage), totalResults);

          let controlsHtml = '<div id="inat-controls" style="margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">';

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

          // Pagination info and buttons (only if not "all")
          if (currentPerPage !== 'all') {
            controlsHtml += '<div style="color: #666;">Page ' + currentPage + '</div>';

            controlsHtml += '<div style="display: flex; gap: 5px;">';
            const hasPrev = currentPage > 1;
            const hasNext = totalResults >= parseInt(currentPerPage);

            if (hasPrev) {
              controlsHtml += '<button id="inat-prev" style="padding: 5px 10px; cursor: pointer;">← Previous</button>';
            }
            if (hasNext) {
              controlsHtml += '<button id="inat-next" style="padding: 5px 10px; cursor: pointer;">Next →</button>';
            }
            controlsHtml += '</div>';
          }

          controlsHtml += '<div style="margin-left: auto; color: #666;">Showing ' + startIndex + '-' + endIndex + ' of ' + totalResults + '+ observations</div>';
          controlsHtml += '</div>';

          // Render observations as grid
          let html = '<div class="inat-observations-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">';
          results.forEach(obs => {
            const species = obs.species_guess || 'Unknown species';
            const place = obs.place_guess || 'Unknown location';
            const date = obs.observed_on || 'Unknown date';
            const id = obs.id || '';

            html += '<div class="inat-observation-card" style="border: 1px solid #ddd; padding: 15px; background: #fff;">';
            html += '<h4 style="margin: 0 0 10px 0; font-size: 16px;">' + escapeHtml(species) + '</h4>';
            html += '<p style="margin: 5px 0; color: #666; font-size: 14px;">📍 ' + escapeHtml(place) + '</p>';
            html += '<p style="margin: 5px 0; color: #666; font-size: 14px;">📅 ' + escapeHtml(date) + '</p>';
            if (id) {
              html += '<p style="margin: 10px 0 0 0;"><a href="https://www.inaturalist.org/observations/' + id + '" target="_blank" style="color: #2271b1;">View on iNaturalist →</a></p>';
            }
            html += '</div>';
          });
          html += '</div>';

          listContainer.innerHTML = controlsHtml + html;

          // Attach event handlers
          const perPageSelect = document.getElementById('inat-per-page');
          if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
              currentPerPage = this.value;
              currentPage = 1;
              fetchObservations();
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
              currentPage++;
              fetchObservations();
            });
          }
        })
        .catch(e => {
          listContainer.innerHTML = '<p style="color: red;">Fetch failed: ' + escapeHtml(e.message) + '</p>';
          console.error('iNat observations fetch error:', e);
        });
    }

    // Initial fetch
    fetchObservations();

    // Hide the old filter container
    filterContainer.parentElement.style.display = 'none';
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})();
