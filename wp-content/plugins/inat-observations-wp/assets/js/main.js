// Client-side enhancement for filters and list rendering.
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const root = document.getElementById('inat-observations-root');
    if(!root) return;

    // Check for localized settings
    if(typeof inatObsSettings === 'undefined') {
      root.querySelector('#inat-list').innerText = 'Configuration error';
      return;
    }

    // Build URL with nonce for CSRF protection
    const url = new URL(inatObsSettings.ajaxUrl);
    url.searchParams.set('action', 'inat_obs_fetch');
    url.searchParams.set('nonce', inatObsSettings.nonce);

    // Fetch observations via AJAX endpoint
    fetch(url)
      .then(r => r.json())
      .then(j => {
        if(!j.success) {
          root.querySelector('#inat-list').innerText = j.data?.message || 'Error loading observations';
          return;
        }
        const data = j.data;
        // TODO: parse data.results and populate filters and list
        root.querySelector('#inat-list').innerText = 'Loaded ' + (data.results ? data.results.length : 0) + ' observations.';
      })
      .catch(e => {
        root.querySelector('#inat-list').innerText = 'Fetch failed';
      });
  });
})();
