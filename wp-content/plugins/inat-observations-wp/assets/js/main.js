// Client-side enhancement for filters and list rendering.
(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const root = document.getElementById('inat-observations-root');
    if(!root) return;
    // Fetch observations via AJAX endpoint
    fetch(ajaxurl + '?action=inat_obs_fetch')
      .then(r => r.json())
      .then(j => {
        if(!j.success) {
          root.querySelector('#inat-list').innerText = 'Error loading observations';
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
