# WORDPRESS-PLUGIN: iNaturalist Observations - Architectural Design

**Project**: inat-observations-wp
**Type**: WordPress Plugin
**Version**: 0.2.0 (Architectural Target)
**Status**: Early Development → Feature-Complete Roadmap
**Last Updated**: 2026-01-06

---

## Vision Statement

**A beautiful, responsive WordPress plugin that displays iNaturalist observations with Material Design UX, intelligent caching, and powerful filtering—making biodiversity data accessible and delightful to explore.**

---

## Core Architectural Principles

### 1. **Lazy Loading with Smart Caching**
- **Database caching**: Observations stored locally to minimize API calls
- **WordPress Cron**: Background refresh on configurable schedule
- **Stale detection**: Automatic refresh when cache expires
- **On-demand fetch**: Initial sync on first config save

### 2. **Minimal Configuration, Maximum Flexibility**
- **At least ONE required**: USER-ID or PROJECT-ID (both optional individually)
- **Sane defaults**: Works out-of-box with minimal config
- **Progressive disclosure**: Advanced options hidden until needed

### 3. **Material Design Throughout**
- **Consistent UX**: All views follow Material Design guidelines
- **Responsive**: Mobile-first, tablet-aware, desktop-optimized
- **Accessibility**: WCAG 2.1 AA compliant
- **Smooth transitions**: View changes feel natural

### 4. **Performance First**
- **Client-side rendering**: Fast, responsive interactions
- **Lazy image loading**: Only load visible thumbnails
- **Pagination**: Handle thousands of observations gracefully
- **XSS-safe**: Images loaded from iNaturalist CDN, not our server

---

## Installation & Database

### On Plugin Activation

**Actions performed**:
1. **Create custom table**: `wp_inat_observations`
   - Stores cached observation data
   - Indexed for fast filtering
   - Normalized structure for DNA metadata
2. **Schedule WP-Cron job**: Daily refresh (configurable)
3. **Create default options**: Empty config (user must configure)
4. **Show admin notice**: "Configure your iNaturalist settings to begin"

**Database Schema** (`wp_inat_observations`):
```sql
CREATE TABLE wp_inat_observations (
    id BIGINT UNSIGNED NOT NULL PRIMARY KEY,           -- iNat observation ID
    user_id BIGINT UNSIGNED,                           -- iNat user ID (contributor)
    user_login VARCHAR(255),                           -- Contributor username
    taxon_name VARCHAR(255),                           -- Species name
    common_name VARCHAR(255),                          -- Common name
    observed_on DATE,                                  -- Observation date
    location_name VARCHAR(500),                        -- Human-readable location
    latitude DECIMAL(10, 8),                           -- GPS latitude
    longitude DECIMAL(11, 8),                          -- GPS longitude
    image_url TEXT,                                    -- Primary image URL
    thumbnail_url TEXT,                                -- Thumbnail URL
    has_dna BOOLEAN DEFAULT FALSE,                     -- DNA metadata flag
    dna_type VARCHAR(50),                              -- DNA type (if available)
    quality_grade VARCHAR(20),                         -- research/needs_id/casual
    observation_field_values LONGTEXT,                 -- JSON blob of metadata
    created_at DATETIME,                               -- When cached locally
    updated_at DATETIME,                               -- Last refresh
    INDEX idx_user_id (user_id),
    INDEX idx_taxon_name (taxon_name),
    INDEX idx_observed_on (observed_on),
    INDEX idx_has_dna (has_dna),
    INDEX idx_quality_grade (quality_grade)
);
```

### WP-Cron Refresh Job

**Schedule**: `daily` (WordPress cron)
**Hook**: `inat_obs_refresh_observations`
**Behavior**:
- Checks if USER-ID or PROJECT-ID configured
- If neither set: Skip refresh (no-op)
- If set: Fetch from iNaturalist API
- **Pagination**: Fetch all pages (max 10,000 observations per iNat limits)
- **Upsert**: Replace existing, insert new
- **Metadata parse**: Extract DNA flag from `observation_field_values`
- **Error handling**: Log failures, don't crash

---

## Configuration Page

**Location**: WordPress Admin → Settings → iNaturalist Observations

**Access**: Admin → Installed Plugins → iNaturalist Observations → Settings

### Form Fields

**Minimalistic Material Design Form** with optional fields:

#### 1. **iNaturalist User ID** (optional, but one required)
- **Type**: Text input (numeric)
- **Label**: "iNaturalist User ID"
- **Placeholder**: "e.g., 123456"
- **Help text**: "Filter observations by specific user. Find your ID in your iNaturalist profile URL."
- **Validation**: Numeric, positive integer

#### 2. **iNaturalist Project ID** (optional, but one required)
- **Type**: Text input (numeric or slug)
- **Label**: "iNaturalist Project ID or Slug"
- **Placeholder**: "e.g., my-project-slug or 12345"
- **Help text**: "Filter observations by project. Find the project slug in the project URL."
- **Validation**: Alphanumeric, hyphens allowed

#### 3. **Refresh Frequency** (optional, default: daily)
- **Type**: Dropdown
- **Options**: Hourly, Twice Daily, Daily (default), Weekly
- **Label**: "Cache Refresh Frequency"
- **Help text**: "How often to check iNaturalist for new observations."

#### 4. **iNaturalist API Token** (optional, for higher rate limits)
- **Type**: Password input
- **Label**: "API Token (optional)"
- **Help text**: "Personal API token for higher rate limits. Not required for public data."
- **Validation**: Alphanumeric, safe storage

### Form Validation

**At least ONE required**:
```php
if (empty($user_id) && empty($project_id)) {
    add_settings_error(
        'inat_obs_settings',
        'missing_config',
        'Please specify at least one: User ID or Project ID.',
        'error'
    );
}
```

### Save Changes Button

**Material Design raised button**:
- Primary color
- Shows spinner on save
- Success toast: "Settings saved. Refreshing observations..."
- Triggers immediate background refresh (via AJAX)

---

## Views Architecture

### View Hierarchy

```
┌─────────────────────────────────────────────┐
│  CONFIG PAGE (Admin Settings)               │
│  - Minimal form                             │
│  - Save Changes button                      │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  FILTER BAR (Top, shared by GRID and LIST)  │
│  - Filter dropdowns (left to right)         │
│  - VIEW TOGGLE (rightmost)                  │
│  - BREADCRUMB PATH (when filters active)    │
└─────────────────────────────────────────────┘
       ↓                      ↓
┌──────────────┐       ┌──────────────┐
│  GRID VIEW   │  ←→   │  LIST VIEW   │
│  (Default)   │       │              │
└──────────────┘       └──────────────┘
       ↓                      ↓
┌─────────────────────────────────────────────┐
│  DETAILS VIEW (full-screen overlay)         │
│  - CLOSE button (top right)                 │
│  - Large image                              │
│  - All metadata                             │
└─────────────────────────────────────────────┘
```

---

## FILTER BAR

**Location**: Top of GRID and LIST views
**Hidden in**: DETAILS view
**Material Design**: Elevated toolbar with shadow

### Filter Components (Left to Right)

1. **Contributor Filter**
   - **Type**: Autocomplete dropdown
   - **Data source**: Pre-populated from cached database (top 50 contributors)
   - **Free text**: Allow typing to search
   - **Placeholder**: "Filter by contributor..."

2. **Location Filter**
   - **Type**: Autocomplete dropdown
   - **Data source**: Pre-populated from cached database (top 50 locations)
   - **Free text**: Allow typing to search
   - **Placeholder**: "Filter by location..."

3. **DNA Filter**
   - **Type**: Checkbox toggle
   - **Label**: "Has DNA"
   - **Icon**: DNA double helix
   - **States**: All (default), Has DNA, No DNA

4. **Quality Grade Filter** (recommended by biology specialist)
   - **Type**: Dropdown
   - **Options**: All (default), Research Grade, Needs ID, Casual
   - **Label**: "Quality"

5. **Taxon Filter** (species/taxon name)
   - **Type**: Autocomplete dropdown
   - **Data source**: Pre-populated from cached database
   - **Free text**: Allow typing to search
   - **Placeholder**: "Filter by species..."

6. **Date Range Filter** (optional, future enhancement)
   - **Type**: Date picker
   - **Label**: "Observed on..."

### VIEW TOGGLE (Rightmost)

**Material Design Icon Toggle**:
- **Grid icon**: When in LIST view (click to switch to GRID)
- **List icon**: When in GRID view (click to switch to LIST)
- **Tooltip**: "Switch to Grid View" / "Switch to List View"
- **Animation**: Smooth fade transition (300ms)

### BREADCRUMB PATH

**Visibility**: Only shown when filters are active
**Location**: Below filter dropdowns, within FILTER BAR
**Material Design**: Chip array with dismiss actions

**Example**:
```
Filter:
  [Contributor: William X]  [Location: San Diego X]  [Has DNA ✓ X]  [Clear All]
```

**Behavior**:
- Each filter = Material Design Chip with × button
- Click × to remove individual filter
- "Clear All" button removes all filters
- Auto-updates on filter change (enter, blur, or Update button)

---

## GRID VIEW (Default)

**Layout**: Responsive thumbnail grid
**Material Design**: Card-based with elevation on hover

### Responsive Breakpoints

| Screen Size | Columns | Thumbnail Size | Gap |
|-------------|---------|----------------|-----|
| Mobile (< 600px) | 2 | 150px | 8px |
| Tablet (600-960px) | 3 | 180px | 12px |
| Desktop (960-1280px) | 4 | 200px | 16px |
| Large (> 1280px) | 5-6 | 200px | 16px |

### Grid Item (Card)

**Components**:
1. **Thumbnail image**:
   - Loaded from iNaturalist CDN (XSS-safe)
   - Lazy loaded (IntersectionObserver)
   - Square aspect ratio (crop to fit)
   - **DNA badge**: Small overlay badge if `has_dna === true`

2. **DNA Badge** (if applicable):
   - **Position**: Top-right corner of thumbnail
   - **Icon**: Cute DNA double helix (custom SVG or emoji 🧬)
   - **Color**: Accent color (e.g., teal or green)
   - **Tooltip**: "Contains DNA data"

3. **Hover overlay**:
   - Species name (taxon_name)
   - Common name (if available)
   - Contributor name
   - Material Design elevation increase

**Click behavior**: Open DETAILS view

---

## LIST VIEW

**Layout**: Vertical list of rows
**Material Design**: List items with dividers

### List Item (Row)

**Structure**: Thumbnail (left) + Metadata (right)

#### Left: Thumbnail
- **Size**: 80px × 80px (fixed)
- **Image**: Loaded from iNaturalist CDN
- **DNA badge**: Small corner badge if `has_dna === true`

#### Right: Metadata Columns

| Column | Data | Width | Notes |
|--------|------|-------|-------|
| **[✓] DNA** | Has DNA indicator | 60px | Checkmark if `has_dna === true`, empty if false |
| **Species** | Taxon name (scientific) | 25% | Bold, primary text |
| **Common Name** | Common name | 20% | Secondary text |
| **Contributor** | User login | 15% | Link to iNat profile |
| **Location** | Location name | 20% | Truncate with ellipsis |
| **Date** | Observed on | 10% | Format: Jan 6, 2026 |
| **Quality** | Quality grade | 10% | Icon: ✓ (research), ? (needs ID), ~ (casual) |

**DNA Column Design**:
- **Icon**: Checkmark (✓) or DNA icon (🧬) if has DNA
- **Color**: Green if DNA present, gray if absent
- **Prominent**: Make it visually clear which observations have DNA

**Responsive behavior**:
- On mobile: Hide Location and Quality columns
- On tablet: Show all except Quality

**Click behavior**: Open DETAILS view

---

## DETAILS VIEW

**Display**: Full-screen overlay (covers FILTER BAR)
**Material Design**: Modal dialog with scrim (dimmed background)

### Layout

**Top Bar**:
- **CLOSE button**: Material Design × icon button (top-right)
- **Title**: Species name + common name

**Content Area** (scrollable):

1. **Large Image**:
   - Loaded from iNaturalist CDN
   - Full-width or max 800px
   - Maintain aspect ratio
   - Lazy loaded

2. **Metadata Grid** (two-column on desktop, single-column on mobile):

| Field | Data | Prominence |
|-------|------|------------|
| **DNA Status** | "Contains DNA data" or "No DNA data" | Badge (large, colorful) |
| **DNA Type** | If available: "Barcode", "Sequencing", etc. | Secondary text |
| **Contributor** | User login + link to iNat profile | Primary |
| **Species** | Taxon name (scientific) | H2 heading |
| **Common Name** | Common name | H3 subheading |
| **Location** | Location name | Primary |
| **Coordinates** | Latitude, Longitude | Secondary (if available) |
| **Observed On** | Date and time | Primary |
| **Quality Grade** | Research/Needs ID/Casual | Badge |
| **Description** | Observation notes | Paragraph |
| **Observation Fields** | All metadata from `observation_field_values` | Expandable list |

3. **Actions** (bottom):
   - **View on iNaturalist**: Link button to original observation
   - **Share**: Copy link (future enhancement)

**Close behavior**:
- Click CLOSE button → Return to previous view (GRID or LIST)
- Preserve filters and scroll position
- ESC key closes

---

## DNA Metadata Detection

**Challenge**: iNaturalist doesn't have a standard "has_dna" field
**Solution**: Learn by doing—inspect `observation_field_values` to detect DNA

### TODO: DNA Detection Research

**File**: `TODO-001-filter-dna-observations.md`

**Research questions**:
1. What observation field names indicate DNA? (e.g., "DNA Barcode", "Genetic Sample", "Sequencing")
2. Are there specific field IDs we can filter on?
3. What values indicate DNA presence? (boolean, text, URL)
4. How to normalize across different field formats?

**Approach**:
1. Fetch sample observations with DNA (manually inspect)
2. Identify common patterns in `observation_field_values`
3. Create detection function: `has_dna_metadata($observation_field_values)`
4. Test on diverse observations
5. Document findings and update schema

**Expected patterns**:
```json
{
  "observation_field_values": [
    {
      "observation_field": {
        "id": 12345,
        "name": "DNA Barcode",
        "datatype": "text"
      },
      "value": "ACTGACTG..."
    }
  ]
}
```

---

## XSS Investigation: Image Loading Strategy

**File**: `TODO-002-xss-investigation.md`

**Goal**: Host metadata locally, load images from iNaturalist CDN

**Benefits**:
- **Security**: No user-uploaded images on our server
- **Performance**: iNaturalist CDN is faster and bigger
- **Storage**: Save disk space (only metadata in DB)
- **Compliance**: No copyright concerns (images stay on iNat)

**Investigation**:
1. **Test**: Load images from iNat CDN in WordPress
   - Check CORS headers
   - Verify HTTPS support
   - Test lazy loading compatibility
2. **XSS risks**: Analyze image URLs for injection vulnerabilities
   - Sanitize URLs before rendering
   - Use WordPress `esc_url()` function
3. **Fallback**: What if iNat CDN is down?
   - Placeholder image
   - Error message
   - Retry logic

**Implementation**:
```php
// Store only URL in database
$thumbnail_url = esc_url($observation['photos'][0]['url']);

// Render in template
<img
  src="<?php echo esc_url($thumbnail_url); ?>"
  loading="lazy"
  alt="<?php echo esc_attr($observation['taxon_name']); ?>"
/>
```

---

## Material Design Guidelines Compliance

**Reference**: https://m3.material.io/

### Typography
- **Font**: Roboto (or system font stack)
- **Scale**: Material Design type scale
  - H1: 96sp
  - H2: 60sp
  - H3: 48sp
  - Body: 16sp
  - Caption: 12sp

### Color Palette
- **Primary**: Teal (#009688) - buttons, links
- **Secondary**: Amber (#FFC107) - accents
- **Background**: White (#FFFFFF)
- **Surface**: Light gray (#FAFAFA)
- **Error**: Red (#F44336)
- **Success**: Green (#4CAF50)

### Spacing
- **Base unit**: 8dp
- **Card padding**: 16dp
- **Grid gap**: 8dp (mobile), 16dp (desktop)

### Elevation
- **Resting**: 2dp (cards)
- **Raised**: 8dp (cards on hover)
- **Modal**: 24dp (details view scrim)

### Transitions
- **Duration**: 300ms (standard)
- **Easing**: Cubic Bezier (0.4, 0.0, 0.2, 1)

### Components
- **Cards**: Material Design Cards for grid items
- **List Items**: Material Design List for list view
- **Buttons**: Raised (primary), Text (secondary)
- **Text Fields**: Outlined style for filters
- **Chips**: For breadcrumb filters
- **Modal**: For details view

---

## Frontend Implementation Strategy

### Technology Stack

**Core**:
- **Vanilla JS** (ES6+): No heavy frameworks for performance
- **CSS3**: Material Design styles
- **WordPress REST API**: AJAX data fetching

**Optional enhancements**:
- **Alpine.js** (lightweight): For reactive UI updates
- **Tailwind CSS** (utility-first): For rapid Material Design prototyping

### File Structure

```
assets/
├── css/
│   ├── material-design.css      # Core MD styles
│   ├── grid-view.css            # Grid layout
│   ├── list-view.css            # List layout
│   ├── details-view.css         # Details modal
│   └── filter-bar.css           # Filter bar styles
├── js/
│   ├── main.js                  # Entry point
│   ├── views/
│   │   ├── grid-view.js         # Grid rendering
│   │   ├── list-view.js         # List rendering
│   │   ├── details-view.js      # Details modal
│   │   └── filter-bar.js        # Filter logic
│   └── utils/
│       ├── api-client.js        # REST API wrapper
│       ├── lazy-loading.js      # Image lazy loading
│       └── state-manager.js     # View state management
└── images/
    ├── dna-badge.svg            # DNA icon
    ├── placeholder.svg          # Image placeholder
    └── icons/                   # Material icons
```

### State Management

**Client-side state**:
```javascript
const state = {
  view: 'grid', // 'grid' | 'list' | 'details'
  filters: {
    contributor: null,
    location: null,
    has_dna: null,
    quality_grade: null,
    taxon: null,
  },
  observations: [],
  selectedObservation: null,
  scrollPosition: 0,
};
```

**Persistence**:
- Save view preference in localStorage
- Save scroll position on view switch
- Restore state on navigation back

---

## API Endpoints

### REST API

**WordPress REST API routes**:

1. **GET `/wp-json/inat/v1/observations`**
   - **Purpose**: Fetch cached observations
   - **Query params**:
     - `per_page` (default: 100)
     - `page` (default: 1)
     - `contributor` (filter by user_login)
     - `location` (filter by location_name)
     - `has_dna` (boolean filter)
     - `quality_grade` (filter by quality_grade)
     - `taxon` (filter by taxon_name)
   - **Response**: JSON array of observations

2. **POST `/wp-json/inat/v1/refresh`** (admin only)
   - **Purpose**: Trigger immediate cache refresh
   - **Response**: Job status

### AJAX Endpoints

**WordPress AJAX actions**:

1. **`wp_ajax_inat_get_observations`**
   - Same as REST endpoint (for backward compatibility)

2. **`wp_ajax_inat_get_filter_options`**
   - **Purpose**: Get autocomplete options for filters
   - **Params**: `field` (contributor, location, taxon)
   - **Response**: Top 50 unique values

---

## Performance Considerations

### Lazy Loading
- **Images**: IntersectionObserver API
- **Load on visible**: Only fetch images in viewport
- **Progressive**: Load as user scrolls

### Pagination
- **Initial load**: 100 observations
- **Infinite scroll**: Load more on scroll bottom
- **Virtual scrolling**: For 1000+ observations (future)

### Caching Strategy
- **Browser cache**: Cache API responses (1 hour)
- **WordPress transients**: Cache DB queries (5 minutes)
- **CDN**: Images served from iNaturalist CDN

### Database Optimization
- **Indexes**: On all filterable columns
- **Query limits**: Always use LIMIT clause
- **Prepared statements**: Prevent SQL injection, improve performance

---

## Accessibility (WCAG 2.1 AA)

### Keyboard Navigation
- **Tab order**: Logical focus flow
- **Enter/Space**: Activate buttons and cards
- **ESC**: Close details view
- **Arrow keys**: Navigate grid items (optional enhancement)

### Screen Readers
- **ARIA labels**: All interactive elements
- **Alt text**: Descriptive image alt text (species name)
- **Live regions**: Announce filter changes

### Color Contrast
- **Text**: 4.5:1 minimum contrast ratio
- **Icons**: 3:1 minimum contrast ratio
- **Focus indicators**: Visible on all interactive elements

### Mobile Accessibility
- **Touch targets**: Minimum 48×48 px
- **Swipe gestures**: For view switching (optional)

---

## Security & Data Privacy

### XSS Protection
- **Sanitize all input**: WordPress `sanitize_text_field()`, `esc_attr()`, `esc_url()`
- **No eval()**: Never use `eval()` in JavaScript
- **CSP headers**: Content Security Policy (future enhancement)

### SQL Injection
- **Prepared statements**: Use `$wpdb->prepare()`
- **Input validation**: Validate all user input

### CSRF Protection
- **Nonces**: WordPress nonces for AJAX requests
- **Verify nonces**: Check nonce on all POST requests

### Data Privacy
- **No PII**: Only public iNaturalist data
- **No cookies**: Unless user consents
- **GDPR compliance**: Allow data export/deletion (if applicable)

---

## Roadmap: Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
- [x] Plugin skeleton
- [x] Database schema
- [x] Basic API fetch
- [ ] **Complete admin settings page** (USER-ID, PROJECT-ID form)
- [ ] **WP-Cron refresh job** (full pagination)
- [ ] **DNA detection research** (TODO-001)

### Phase 2: Core Views (Weeks 3-4)
- [ ] **GRID VIEW**: Responsive thumbnail grid
- [ ] **LIST VIEW**: Tabular layout with DNA column
- [ ] **VIEW TOGGLE**: Switch between GRID and LIST
- [ ] **Basic filtering**: Contributor, Location (no autocomplete yet)

### Phase 3: Advanced Filtering (Weeks 5-6)
- [ ] **FILTER BAR**: Material Design toolbar
- [ ] **Autocomplete dropdowns**: Pre-populated options
- [ ] **DNA filter**: Checkbox toggle
- [ ] **BREADCRUMB PATH**: Active filter chips
- [ ] **Filter persistence**: Save in URL params

### Phase 4: Details & Polish (Weeks 7-8)
- [ ] **DETAILS VIEW**: Full-screen modal
- [ ] **DNA badge**: Cute icon overlay on thumbnails
- [ ] **Material Design polish**: Typography, colors, spacing
- [ ] **Lazy loading**: IntersectionObserver for images
- [ ] **XSS investigation** (TODO-002)

### Phase 5: Performance & Testing (Weeks 9-10)
- [ ] **Pagination/infinite scroll**
- [ ] **Performance optimization**: Caching, indexes
- [ ] **Accessibility audit**: WCAG 2.1 AA
- [ ] **Unit tests**: PHP and JavaScript
- [ ] **User testing**: Feedback and iteration

### Phase 6: Launch Prep (Week 11-12)
- [ ] **Documentation**: User guide, developer docs
- [ ] **WordPress.org submission**: `readme.txt`, screenshots
- [ ] **Marketing**: Demo site, blog post
- [ ] **Launch** 🚀

---

## Success Metrics

### User Experience
- **Page load time**: < 2 seconds
- **Image load time**: < 500ms (lazy loaded)
- **Filter response**: < 200ms (client-side)
- **Mobile usability**: 95+ Lighthouse score

### Data Quality
- **Cache freshness**: 95% of requests served from cache
- **DNA detection accuracy**: 90%+ precision
- **API uptime**: 99.5% (iNaturalist dependency)

### Adoption
- **Active installs**: 100+ (first 6 months)
- **User ratings**: 4.5+ stars (WordPress.org)
- **Support requests**: < 5% of users

---

## Future Enhancements

### Advanced Features
- [ ] **Map view**: Show observations on interactive map
- [ ] **Calendar view**: Group by observed_on date
- [ ] **Export**: CSV/JSON export of filtered observations
- [ ] **Social sharing**: Share individual observations
- [ ] **User favorites**: Bookmark observations
- [ ] **Comments**: Allow WordPress users to comment

### Integrations
- [ ] **Gutenberg blocks**: Native WordPress editor integration
- [ ] **Elementor widget**: Page builder support
- [ ] **WooCommerce**: Sell observation prints (if applicable)

### Analytics
- [ ] **View tracking**: Most viewed observations
- [ ] **Filter analytics**: Most used filters
- [ ] **User behavior**: Heatmaps, session recordings

---

## Related Documents

- **TODO-main.md**: Current project status and tasks
- **TODO-001-filter-dna-observations.md**: DNA detection research
- **TODO-002-xss-investigation.md**: Image loading security
- **TODO-ARCHITECT.md**: Architectural decisions
- **TODO-UX.md**: UX design specifications
- **TODO-SECURITY.md**: Security considerations
- **README.md**: Project overview and setup

---

**Philosophy**: "Make biodiversity data delightful to explore. Every interaction should feel smooth, intuitive, and beautiful."

**Design Principle**: "Progressive disclosure. Simple by default, powerful when needed."

**Technical Principle**: "Leverage existing infrastructure. WordPress + iNaturalist = win-win."

---

**Last Updated**: 2026-01-06
**Next Review**: After Phase 1 completion
