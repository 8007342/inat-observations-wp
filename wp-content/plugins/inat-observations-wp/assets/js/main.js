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

    // Build URL with nonce for CSRF protection
    const url = new URL(inatObsSettings.ajaxUrl);
    url.searchParams.set('action', 'inat_obs_fetch');
    url.searchParams.set('nonce', inatObsSettings.nonce);
    url.searchParams.set('per_page', inatObsSettings.perPage || 50);

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
          filterContainer.innerHTML = '<option value="">No data</option>';
          return;
        }

        // Hide filter for now (TODO: implement filtering)
        filterContainer.parentElement.style.display = 'none';

        // Render observations as simple list
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

        listContainer.innerHTML = '<p style="margin-bottom: 15px;"><strong>Showing ' + results.length + ' observations</strong></p>' + html;
      })
      .catch(e => {
        listContainer.innerHTML = '<p style="color: red;">Fetch failed: ' + escapeHtml(e.message) + '</p>';
        console.error('iNat observations fetch error:', e);
      });
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
})();
