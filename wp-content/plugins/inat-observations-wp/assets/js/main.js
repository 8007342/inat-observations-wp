/**
 * iNaturalist Observations WordPress Plugin
 * Main JavaScript Module
 *
 * Accessibility Features:
 * - ARIA live region announcements for dynamic content
 * - Keyboard navigation support
 * - Focus management for loaded content
 * - Screen reader status updates
 * - Progressive enhancement (works without JS)
 *
 * @package inat-observations-wp
 * @version 0.1.0
 */

(function () {
    'use strict';

    /**
     * Configuration object from WordPress localization
     * Falls back to defaults if not available
     */
    var config = window.inatObsConfig || {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        project: '',
        perPage: 50,
        i18n: {
            loading: 'Loading observations...',
            loadingFilters: 'Loading filters...',
            filterByField: 'Filter by observation field',
            allFields: 'All observation fields',
            errorLoading: 'Unable to load observations. Please try refreshing the page.',
            errorNetwork: 'Network error. Please check your connection and try again.',
            observationsLoaded: 'Loaded %d observations.',
            noObservations: 'No observations found.',
            skipToObservations: 'Skip to observations list'
        }
    };

    /**
     * DOM element references - populated on init
     */
    var elements = {
        root: null,
        list: null,
        filterSelect: null,
        statusRegion: null
    };

    /**
     * State management
     */
    var state = {
        observations: [],
        fields: [],
        currentFilter: '',
        isLoading: false,
        hasError: false
    };

    /**
     * Initialize the observations widget
     * Called when DOM is ready
     */
    function init() {
        // Get root element - exit if not found (shortcode not on page)
        elements.root = document.getElementById('inat-observations-root');
        if (!elements.root) {
            return;
        }

        // Cache DOM references
        elements.list = document.getElementById('inat-list');
        elements.filterSelect = document.getElementById('inat-filter-field');
        elements.statusRegion = document.getElementById('inat-status');

        // Set up event listeners
        setupEventListeners();

        // Fetch initial data
        fetchObservations();
    }

    /**
     * Set up event listeners for interactive elements
     */
    function setupEventListeners() {
        // Filter select change handler
        if (elements.filterSelect) {
            elements.filterSelect.addEventListener('change', handleFilterChange);
        }

        // Keyboard navigation for observation cards (delegated)
        if (elements.list) {
            elements.list.addEventListener('keydown', handleListKeydown);
        }
    }

    /**
     * Handle filter dropdown change
     * @param {Event} event - Change event
     */
    function handleFilterChange(event) {
        state.currentFilter = event.target.value;
        renderObservations();

        // Announce filter change to screen readers
        var filterName = state.currentFilter || config.i18n.allFields;
        announceStatus('Filtering by: ' + filterName);
    }

    /**
     * Handle keyboard navigation within observation list
     * Implements roving tabindex pattern for card navigation
     * @param {KeyboardEvent} event - Keydown event
     */
    function handleListKeydown(event) {
        var cards = elements.list.querySelectorAll('.inat-observation-card');
        if (cards.length === 0) return;

        var currentCard = event.target.closest('.inat-observation-card');
        if (!currentCard) return;

        var currentIndex = Array.from(cards).indexOf(currentCard);
        var targetIndex = -1;

        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowRight':
                event.preventDefault();
                targetIndex = Math.min(currentIndex + 1, cards.length - 1);
                break;
            case 'ArrowUp':
            case 'ArrowLeft':
                event.preventDefault();
                targetIndex = Math.max(currentIndex - 1, 0);
                break;
            case 'Home':
                event.preventDefault();
                targetIndex = 0;
                break;
            case 'End':
                event.preventDefault();
                targetIndex = cards.length - 1;
                break;
        }

        if (targetIndex >= 0 && targetIndex !== currentIndex) {
            // Move focus to target card's first focusable element
            var targetCard = cards[targetIndex];
            var focusable = targetCard.querySelector('a, button, [tabindex="0"]');
            if (focusable) {
                focusable.focus();
            } else {
                targetCard.focus();
            }
        }
    }

    /**
     * Announce status message to screen readers via ARIA live region
     * @param {string} message - Message to announce
     */
    function announceStatus(message) {
        if (!elements.statusRegion) return;

        // Clear and re-set to ensure announcement
        elements.statusRegion.textContent = '';
        // Use setTimeout to ensure the DOM update triggers announcement
        setTimeout(function () {
            elements.statusRegion.textContent = message;
        }, 50);
    }

    /**
     * Fetch observations from AJAX endpoint
     */
    function fetchObservations() {
        if (state.isLoading) return;

        state.isLoading = true;
        state.hasError = false;

        // Update aria-busy state
        if (elements.list) {
            elements.list.setAttribute('aria-busy', 'true');
        }

        // Announce loading state
        announceStatus(config.i18n.loading);

        // Build AJAX URL with nonce for security
        var url = config.ajaxUrl + '?action=inat_obs_fetch';
        if (config.nonce) {
            url += '&nonce=' + encodeURIComponent(config.nonce);
        }
        if (config.project) {
            url += '&project=' + encodeURIComponent(config.project);
        }
        if (config.perPage) {
            url += '&per_page=' + encodeURIComponent(config.perPage);
        }

        fetch(url)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.json();
            })
            .then(function (json) {
                if (!json.success) {
                    throw new Error(json.data && json.data.message ? json.data.message : 'Unknown error');
                }
                handleFetchSuccess(json.data);
            })
            .catch(function (error) {
                handleFetchError(error);
            })
            .finally(function () {
                state.isLoading = false;
                if (elements.list) {
                    elements.list.setAttribute('aria-busy', 'false');
                }
            });
    }

    /**
     * Handle successful data fetch
     * @param {Object} data - API response data
     */
    function handleFetchSuccess(data) {
        state.observations = data.results || [];
        state.hasError = false;

        // Extract unique observation fields for filter dropdown
        extractFields();

        // Populate filter dropdown
        populateFilterDropdown();

        // Render observations
        renderObservations();

        // Announce success to screen readers
        var count = state.observations.length;
        var message = config.i18n.observationsLoaded.replace('%d', count);
        announceStatus(message);
    }

    /**
     * Handle fetch error
     * @param {Error} error - Error object
     */
    function handleFetchError(error) {
        state.hasError = true;
        state.observations = [];

        // Debug logging removed for production - error handled in UI

        // Render error state
        renderError(error.message.includes('network') ?
            config.i18n.errorNetwork :
            config.i18n.errorLoading
        );

        // Announce error to screen readers
        announceStatus(config.i18n.errorLoading);
    }

    /**
     * Extract unique observation field names for filtering
     */
    function extractFields() {
        var fieldSet = {};

        state.observations.forEach(function (obs) {
            if (obs.observation_field_values && Array.isArray(obs.observation_field_values)) {
                obs.observation_field_values.forEach(function (field) {
                    if (field.observation_field && field.observation_field.name) {
                        fieldSet[field.observation_field.name] = true;
                    }
                });
            }
        });

        state.fields = Object.keys(fieldSet).sort();
    }

    /**
     * Populate filter dropdown with extracted fields
     */
    function populateFilterDropdown() {
        if (!elements.filterSelect) return;

        // Clear existing options
        elements.filterSelect.innerHTML = '';

        // Add "All" option
        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = config.i18n.allFields;
        elements.filterSelect.appendChild(allOption);

        // Add field options
        state.fields.forEach(function (fieldName) {
            var option = document.createElement('option');
            option.value = fieldName;
            option.textContent = fieldName;
            elements.filterSelect.appendChild(option);
        });

        // Enable the select
        elements.filterSelect.disabled = false;
    }

    /**
     * Render observations to the list container
     */
    function renderObservations() {
        if (!elements.list) return;

        // Filter observations if filter is active
        var filtered = state.currentFilter ?
            filterByField(state.observations, state.currentFilter) :
            state.observations;

        // Handle empty state
        if (filtered.length === 0) {
            renderEmptyState();
            return;
        }

        // Build results count
        var countHtml = '<p class="inat-results-count">' +
            'Showing <strong>' + filtered.length + '</strong> ' +
            (filtered.length === 1 ? 'observation' : 'observations') +
            (state.currentFilter ? ' with field "' + escapeHtml(state.currentFilter) + '"' : '') +
            '</p>';

        // Build grid container
        var gridHtml = '<ul class="inat-observations-grid" role="list">';

        filtered.forEach(function (obs, index) {
            gridHtml += renderObservationCard(obs, index);
        });

        gridHtml += '</ul>';

        elements.list.innerHTML = countHtml + gridHtml;
    }

    /**
     * Render a single observation card
     * @param {Object} obs - Observation data
     * @param {number} index - Card index for accessibility
     * @returns {string} HTML string
     */
    function renderObservationCard(obs, index) {
        var speciesName = obs.species_guess || obs.taxon_name || 'Unknown species';
        var location = obs.place_guess || 'Unknown location';
        var date = formatDate(obs.observed_on);
        var imageUrl = getObservationImage(obs);
        var observationUrl = 'https://www.inaturalist.org/observations/' + obs.id;

        var html = '<li class="inat-observation-card" aria-labelledby="obs-title-' + obs.id + '">';

        // Image container
        html += '<div class="inat-observation-image-container">';
        if (imageUrl) {
            html += '<img class="inat-observation-image" ' +
                'src="' + escapeHtml(imageUrl) + '" ' +
                'alt="Photo of ' + escapeHtml(speciesName) + '" ' +
                'loading="lazy">';
        } else {
            html += '<div class="inat-observation-no-image" aria-hidden="true">' +
                '<span>No photo available</span>' +
                '</div>';
        }
        html += '</div>';

        // Content section
        html += '<div class="inat-observation-content">';

        // Species name as heading with link
        html += '<h3 class="inat-observation-species" id="obs-title-' + obs.id + '">';
        html += '<a href="' + escapeHtml(observationUrl) + '" ' +
            'class="inat-observation-species-link" ' +
            'target="_blank" ' +
            'rel="noopener noreferrer">' +
            escapeHtml(speciesName) +
            '<span class="screen-reader-text"> (opens in new tab)</span>' +
            '</a>';
        html += '</h3>';

        // Meta information
        html += '<div class="inat-observation-meta">';
        html += '<p class="inat-observation-location">' +
            '<span aria-hidden="true">&#128205;</span> ' +
            '<span>' + escapeHtml(location) + '</span>' +
            '</p>';
        html += '<p class="inat-observation-date">' +
            '<span aria-hidden="true">&#128197;</span> ' +
            '<time datetime="' + escapeHtml(obs.observed_on || '') + '">' + escapeHtml(date) + '</time>' +
            '</p>';
        html += '</div>';

        // Observation fields (if any)
        if (obs.observation_field_values && obs.observation_field_values.length > 0) {
            html += '<div class="inat-observation-fields" aria-label="Observation fields">';
            obs.observation_field_values.slice(0, 3).forEach(function (field) {
                var fieldName = field.observation_field ? field.observation_field.name : 'Field';
                var fieldValue = field.value || '';
                html += '<span class="inat-field-tag" title="' + escapeHtml(fieldName) + ': ' + escapeHtml(fieldValue) + '">' +
                    escapeHtml(fieldValue) +
                    '</span>';
            });
            if (obs.observation_field_values.length > 3) {
                html += '<span class="inat-field-tag">+' + (obs.observation_field_values.length - 3) + ' more</span>';
            }
            html += '</div>';
        }

        html += '</div>'; // .inat-observation-content
        html += '</li>';

        return html;
    }

    /**
     * Render empty state message
     */
    function renderEmptyState() {
        var html = '<div class="inat-message inat-message-info" role="status">' +
            '<span class="inat-message-icon" aria-hidden="true">&#128269;</span>' +
            '<p class="inat-message-text">' + escapeHtml(config.i18n.noObservations) + '</p>';

        if (state.currentFilter) {
            html += '<p class="inat-message-help">Try selecting a different filter or view all observations.</p>';
        }

        html += '</div>';

        elements.list.innerHTML = html;
    }

    /**
     * Render error state with retry button
     * @param {string} message - Error message to display
     */
    function renderError(message) {
        var html = '<div class="inat-message inat-message-error" role="alert">' +
            '<span class="inat-message-icon" aria-hidden="true">&#9888;</span>' +
            '<p class="inat-message-text">' + escapeHtml(message) + '</p>' +
            '<p class="inat-message-help">If the problem persists, please contact the site administrator.</p>' +
            '<button type="button" class="inat-retry-button" aria-label="Retry loading observations">' +
            'Try Again' +
            '</button>' +
            '</div>';

        elements.list.innerHTML = html;

        // Add retry button handler
        var retryButton = elements.list.querySelector('.inat-retry-button');
        if (retryButton) {
            retryButton.addEventListener('click', function () {
                fetchObservations();
            });
            // Focus the retry button for keyboard users
            retryButton.focus();
        }
    }

    /**
     * Filter observations by field name
     * @param {Array} observations - All observations
     * @param {string} fieldName - Field name to filter by
     * @returns {Array} Filtered observations
     */
    function filterByField(observations, fieldName) {
        return observations.filter(function (obs) {
            if (!obs.observation_field_values || !Array.isArray(obs.observation_field_values)) {
                return false;
            }
            return obs.observation_field_values.some(function (field) {
                return field.observation_field &&
                    field.observation_field.name === fieldName;
            });
        });
    }

    /**
     * Get the best available image URL for an observation
     * @param {Object} obs - Observation data
     * @returns {string|null} Image URL or null
     */
    function getObservationImage(obs) {
        if (obs.photos && obs.photos.length > 0 && obs.photos[0].url) {
            // Replace 'square' with 'medium' for better quality
            return obs.photos[0].url.replace('/square.', '/medium.');
        }
        if (obs.taxon && obs.taxon.default_photo && obs.taxon.default_photo.medium_url) {
            return obs.taxon.default_photo.medium_url;
        }
        return null;
    }

    /**
     * Format date for display
     * @param {string} dateStr - ISO date string
     * @returns {string} Formatted date
     */
    function formatDate(dateStr) {
        if (!dateStr) return 'Unknown date';

        try {
            var date = new Date(dateStr);
            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        } catch (e) {
            return dateStr;
        }
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Initialize when DOM is ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
