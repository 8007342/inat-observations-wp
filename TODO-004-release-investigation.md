# TODO-004: WordPress Plugin Release Investigation

**Status**: Research Complete - Implementation Pending
**Created**: 2026-01-06
**Plugin**: inat-observations-wp
**Current Version**: 0.1.0

## Executive Summary

This document outlines the research findings and actionable steps for releasing the iNat Observations WordPress plugin to the public. Based on 2026 best practices, there are multiple distribution paths available, each with different trade-offs for installation ease, maintenance burden, and user reach.

---

## 1. WordPress.org Plugin Directory (Recommended for Maximum Reach)

### Overview
The official WordPress.org plugin directory provides the widest distribution, one-click installation from WordPress admin, automatic update notifications, and built-in security review process. As of 2025, plugin submissions have doubled, and the review process has been streamlined with automated Plugin Check tools.

### 1.1 Submission Process

**Prerequisites:**
- [ ] WordPress.org account (use official email if submitting for organization)
- [ ] Plugin must be GPL v2 (or later) compatible
- [ ] Complete, working plugin ZIP file ready for submission
- [ ] readme.txt file following WordPress standards

**Process Timeline:**
- Initial review: 1-10 business days (target: 5 days)
- Total approval time: 14 business days maximum
- Automated checks reduce issues by 41% when approving plugins

**Steps:**
1. [ ] Sign up for WordPress.org account at https://wordpress.org/
2. [ ] Submit plugin at https://wordpress.org/plugins/developers/add/
3. [ ] Provide brief overview of plugin functionality
4. [ ] Upload complete plugin ZIP file
5. [ ] Wait for manual code review
6. [ ] Address any review feedback or corrections
7. [ ] Receive SVN repository access upon approval

**Important Notes:**
- Plugin submissions undergo manual code review for security and quality
- WordPress.org integrated Plugin Check Plugin in September 2024 for automatic reviews
- Plugin must not do anything illegal or be morally offensive
- All PHP code must be GPL-compatible; CSS/artwork may use other licenses

### 1.2 Creating readme.txt

**Required Header Fields:**
```
=== inat-observations-wp ===
Contributors: ayahuitltlatoani (replace with WordPress.org username)
Tags: inaturalist, observations, biodiversity, nature, science
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Short description here (max 150 characters without markup)
```

**Required Sections:**
- [ ] Description (full plugin description)
- [ ] Installation (step-by-step installation instructions)
- [ ] Frequently Asked Questions
- [ ] Screenshots (with numbered captions)
- [ ] Changelog (version history)
- [ ] Upgrade Notice (important notes for updates)

**Format Notes:**
- Uses customized Markdown format
- Plugin name written between triple equals signs (=== Name ===)
- Section headers use double equals signs (== Section ==)
- Contributors must be comma-separated WordPress.org usernames
- Tags: comma-separated, apply to plugin categorization
- Short description appears under plugin name (gets cut off if >150 chars)

**Validation:**
- [ ] Validate readme.txt at https://wordpress.org/plugins/readme.txt
- [ ] Use WordPress Readme Validator to ensure proper formatting

**Tasks:**
- [ ] Create readme.txt file in plugin root directory
- [ ] Add all required header fields
- [ ] Write short description (< 150 characters)
- [ ] Document installation steps
- [ ] Add FAQ section (common questions about iNaturalist API usage)
- [ ] Create changelog for version 0.1.0
- [ ] Validate format with WordPress Readme Validator

### 1.3 SVN Repository Setup

**Repository Structure:**
```
https://plugins.svn.wordpress.org/inat-observations-wp/
├── assets/          # Screenshots, icons, banners
│   ├── banner-772x250.png (or .jpg)
│   ├── banner-1544x500.png (HiDPI)
│   ├── icon-128x128.png
│   ├── icon-256x256.png (HiDPI, or use SVG)
│   └── screenshot-1.png, screenshot-2.png, etc.
├── trunk/           # Development version (active code)
│   ├── inat-observations-wp.php
│   ├── readme.txt
│   ├── includes/
│   └── ...all plugin files...
└── tags/            # Release versions
    ├── 0.1.0/
    ├── 0.2.0/
    └── ...
```

**Critical SVN Rules:**
- **DO NOT** put main plugin file in subfolder (e.g., /trunk/my-plugin/file.php) - breaks downloads
- SVN is a RELEASE repository - don't commit every small change like Git
- Only push finished, tested changes
- Uppercase filenames won't work - use lowercase only

**Initial SVN Setup:**
```bash
# 1. Install SVN on your system (OS-specific)
sudo dnf install subversion  # Fedora/RHEL
# sudo apt install subversion  # Debian/Ubuntu

# 2. Check out repository (after approval email)
svn co https://plugins.svn.wordpress.org/inat-observations-wp inat-observations-wp-svn

# 3. Authentication
# Username: WordPress.org forums username
# Password: Set SVN-specific password in Account Settings at wordpress.org
```

**Tasks:**
- [ ] Install SVN on development machine
- [ ] Wait for approval email with SVN URL
- [ ] Set up SVN-specific password in WordPress.org account
- [ ] Check out SVN repository
- [ ] Copy plugin files to trunk/ directory
- [ ] Create assets for icons, banners, screenshots
- [ ] Commit initial version to trunk
- [ ] Create first tag (0.1.0) from trunk

### 1.4 Plugin Assets (Visual Identity)

**Icons:**
- **Size**: 128x128px (standard), 256x256px (HiDPI/retina)
- **Format**: PNG, JPG, or SVG (SVG recommended for scalability)
- **Filename**: icon-128x128.png, icon-256x256.png, or icon.svg
- **Max Size**: 1MB (smaller is better)
- **Usage**: Appears in plugin search results and admin pages

**Banners:**
- **Size**: 772x250px (standard), 1544x500px (HiDPI/retina)
- **Format**: PNG or JPG
- **Filename**: banner-772x250.png, banner-1544x500.png
- **Max Size**: 1MB (low-res), 2MB (high-res)
- **RTL Support**: Add -rtl suffix for right-to-left languages (e.g., banner-772x250-rtl.png)
- **Localization**: Can create language-specific banners

**Screenshots:**
- **Purpose**: Illustrate plugin admin dashboard or live examples
- **Format**: PNG or JPG
- **Filename**: screenshot-1.png, screenshot-2.png, etc.
- **Max Size**: 10MB per screenshot
- **Captions**: Correspond to numbered lines in readme.txt Screenshots section
- **Naming**: Lowercase, numbered sequentially

**General Requirements:**
- All filenames must be lowercase (UPPERCASE WON'T WORK)
- Image dimensions must be exact (banner-772x250.png must be exactly 772x250)
- Images served through CDN with heavy caching (updates may take time)
- Place all assets in /assets directory (top-level, like /trunk)

**Tasks:**
- [ ] Design plugin icon (128x128 and 256x256, or SVG)
- [ ] Create banner graphics (772x250 and 1544x500)
- [ ] Take screenshots of plugin features
  - [ ] Screenshot 1: Plugin settings page
  - [ ] Screenshot 2: Shortcode example on page
  - [ ] Screenshot 3: Filter dropdowns in action
  - [ ] Screenshot 4: Observation display example
- [ ] Write screenshot captions in readme.txt
- [ ] Upload all assets to /assets directory in SVN
- [ ] Verify all filenames are lowercase
- [ ] Verify image dimensions are exact

### 1.5 Plugin Header Comments (Main PHP File)

**Current Header (from inat-observations-wp.php):**
```php
/**
 * Plugin Name: inat-observations-wp
 * Plugin URI:  https://github.com/8007342/inat-observations-wp
 * Description: Fetch, cache, and display iNaturalist observations with metadata filtering.
 * Version:     0.1.0
 * Author:      Ayahuitl Tlatoani
 * License:     GPLv2 or later
 * Text Domain: inat-observations-wp
 */
```

**Review Checklist:**
- [x] Plugin Name present
- [x] Plugin URI present (currently GitHub)
- [x] Description present
- [x] Version present (0.1.0)
- [x] Author present
- [ ] Author URI (optional, but recommended)
- [x] License (GPLv2 or later)
- [ ] License URI (should add)
- [x] Text Domain (for internationalization)
- [ ] Domain Path (if translations in non-standard location)
- [ ] Network (true if multisite compatible)

**Recommended Updates:**
```php
/**
 * Plugin Name: iNaturalist Observations
 * Plugin URI:  https://github.com/8007342/inat-observations-wp
 * Description: Fetch, cache, and display iNaturalist observations with metadata filtering. Supports any iNaturalist project via configurable project ID.
 * Version:     0.1.0
 * Author:      Ayahuitl Tlatoani
 * Author URI:  https://github.com/8007342
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inat-observations-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 */
```

**Tasks:**
- [ ] Update plugin name to "iNaturalist Observations" (more user-friendly)
- [ ] Add Author URI
- [ ] Add License URI
- [ ] Add Domain Path
- [ ] Add "Requires at least" header
- [ ] Add "Tested up to" header
- [ ] Add "Requires PHP" header

---

## 2. GitHub Releases (Current Self-Hosted Option)

### Overview
GitHub releases provide version control integration, asset hosting, and update mechanisms for self-hosted plugins. This path gives maximum control but requires manual installation by users (no one-click install from WordPress admin).

### 2.1 Advantages of GitHub Distribution

**Benefits:**
- Full control over code and release timing
- No WordPress.org review process or waiting period
- Use Git workflow instead of SVN
- Suitable for beta testing before official release
- Can offer premium/private versions
- Integrated with development workflow

**Drawbacks:**
- No one-click installation from WordPress admin
- Users must manually download and upload ZIP file
- No automatic update notifications (requires custom update script)
- Limited discoverability (users must find plugin externally)
- Requires more technical knowledge from users

### 2.2 GitHub Release Process

**Creating Releases:**
```bash
# 1. Tag version in Git
git tag -a v0.1.0 -m "Initial public release"
git push origin v0.1.0

# 2. Create release on GitHub
# - Go to https://github.com/8007342/inat-observations-wp/releases
# - Click "Draft a new release"
# - Select tag v0.1.0
# - Add release title and notes
# - Upload plugin ZIP file as release asset (optional, GitHub auto-generates)
```

**Release ZIP Contents:**
- Include only necessary plugin files (exclude development files)
- Use .distignore or .gitattributes with export-ignore directive
- Exclude: .git/, .github/, node_modules/, tests/, .env.example, etc.
- Include: All PHP files, readme.txt, LICENSE, assets for plugin

**Tasks:**
- [ ] Create .distignore file to exclude development files
- [ ] Document manual installation process in README.md
- [ ] Create release notes template
- [ ] Tag version 0.1.0 in Git
- [ ] Create GitHub release with release notes
- [ ] Upload plugin ZIP as release asset (if custom build needed)
- [ ] Add installation instructions to release notes

### 2.3 Self-Hosted Update Mechanism

To enable automatic updates for self-hosted plugins, you need custom update checking code:

**Implementation Options:**
1. **Manual approach**: Write custom update checker that queries GitHub API
2. **Library approach**: Use existing solutions:
   - [Plugin Update Checker by YahnisElsts](https://github.com/YahnisElsts/plugin-update-checker)
   - [WordPress GitHub Plugin Updater](https://github.com/afragen/github-updater)

**Update Flow:**
1. Plugin checks GitHub API for latest release
2. Compares installed version with latest version
3. If newer version available, integrates with WordPress update system
4. User sees update notification in WordPress admin
5. One-click update downloads from GitHub and installs

**Tasks:**
- [ ] Research and select update library (recommend Plugin Update Checker)
- [ ] Integrate update checker into plugin
- [ ] Test update mechanism locally
- [ ] Document update process for users
- [ ] Add FAQ about automatic updates

---

## 3. Hybrid Approach: GitHub + WordPress.org

### Overview
The recommended approach for serious plugin development: develop on GitHub, deploy to WordPress.org via automated GitHub Actions.

### 3.1 GitHub Actions for WordPress.org Deployment

**Popular Action**: [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy)

**Workflow Features:**
- Automatically commits Git tag contents to WordPress.org SVN
- Uses same tag name on both platforms
- Supports .distignore for file exclusion
- Can generate ZIP files from SVN trunk
- Dry-run mode for debugging
- Build directory support for compiled assets

**Example Workflow (.github/workflows/deploy.yml):**
```yaml
name: Deploy to WordPress.org

on:
  push:
    tags:
      - "*"

jobs:
  tag:
    name: New tag
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: WordPress Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          BUILD_DIR: ./build  # Optional: if you have build step
        with:
          generate-zip: true
```

**Configuration Options:**
- `generate-zip`: Create ZIP file from SVN trunk (default: false)
- `BUILD_DIR`: Directory with built plugin files
- `dry-run`: Skip final SVN commit for debugging (default: false)
- `.distignore`: Exclude files from deployment (build files, tests, etc.)

**Tasks:**
- [ ] Create .github/workflows directory
- [ ] Add deploy.yml workflow file
- [ ] Create .distignore file
- [ ] Add SVN credentials to GitHub Secrets
  - [ ] SVN_USERNAME (WordPress.org username)
  - [ ] SVN_PASSWORD (SVN-specific password from wordpress.org)
- [ ] Test workflow with dry-run: true
- [ ] Tag release and verify automatic deployment

### 3.2 Dual Repository Benefits

**Development Benefits:**
- Work in Git (familiar workflow, pull requests, code review)
- Automated deployment to SVN (no manual svn commits)
- Single source of truth (Git is primary, SVN is mirror)
- CI/CD integration (run tests before deployment)

**User Benefits:**
- One-click installation from WordPress.org
- Automatic updates through WordPress admin
- Transparent development (can view code on GitHub)
- Community can contribute via GitHub pull requests

**Maintenance:**
- Tag release in Git → automatically deployed to WordPress.org
- readme.txt changes propagate automatically
- Asset updates sync to WordPress.org
- Changelog maintained in one place

---

## 4. Version Numbering and Semantic Versioning

### 4.1 SemVer for WordPress Plugins

**Format**: MAJOR.MINOR.PATCH (e.g., 1.2.3)

**Increment Rules:**
- **MAJOR**: Breaking changes, incompatible API changes, backwards incompatibility
- **MINOR**: New features, backward-compatible functionality additions
- **PATCH**: Bug fixes, backwards-compatible fixes only

**WordPress-Specific Rules:**
- Don't increment version just for updating "Tested up to" field in readme.txt
- Don't increment version for other metadata changes in readme.txt
- DO increment version when adding new translations (best practice)
- Start with 0.1.0 for initial development, 1.0.0 for first stable public release

**Example Version Progression:**
- 0.1.0 - Initial development release (current)
- 0.2.0 - Add new metadata filter features
- 0.2.1 - Fix caching bug
- 1.0.0 - First stable public release
- 1.1.0 - Add REST API endpoint
- 1.1.1 - Fix API rate limit handling
- 2.0.0 - Change shortcode attribute structure (breaking change)

**Communication:**
- Use SemVer to communicate change impact to users
- Breaking changes (MAJOR bump) need clear upgrade notices
- Document all changes in Changelog section
- Provide upgrade path for breaking changes

**Tasks:**
- [ ] Document current version as 0.1.0 (development/beta)
- [ ] Plan version 1.0.0 release criteria
- [ ] Create version numbering policy document
- [ ] Add version constants to main plugin file (already exists: INAT_OBS_VERSION)
- [ ] Establish changelog format
- [ ] Document breaking change policy

### 4.2 Changelog Format

**Recommended Format (Keep a Changelog style):**
```
== Changelog ==

= 0.1.0 =
Release Date: 2026-01-15

**Added**
* Initial public release
* Fetch observations from iNaturalist API
* Parse observation_field_values metadata
* Cache API results to avoid rate limits
* Filter dropdowns for metadata fields
* Shortcode [inat_observations] for embedding
* REST endpoint for external integrations

**Fixed**
* N/A - initial release

**Changed**
* N/A - initial release

**Security**
* Implement nonce verification for API calls
* Sanitize all user inputs
* Escape all outputs
```

**Tasks:**
- [ ] Create changelog for version 0.1.0
- [ ] Document all current features as "Added" items
- [ ] Use categories: Added, Fixed, Changed, Deprecated, Removed, Security
- [ ] Include release dates for each version
- [ ] Keep changelog in both readme.txt and CHANGELOG.md (for GitHub)

---

## 5. GPL License Compliance

### 5.1 GPL Requirements for WordPress Plugins

**Core Requirements:**
- All PHP code MUST be GPL v2 (or later) compatible
- WordPress.org requires explicit GPL compatibility
- CSS and artwork MAY use other licenses but not required to
- Third-party code must also be GPL-compliant

**Compatible Licenses:**
- GPL v2 or later (recommended - matches WordPress core)
- LGPL (Lesser GPL) - more permissive, allows linking with proprietary code
- MIT License - compatible with GPL
- BSD License - compatible with GPL

**Current Plugin License:**
- Header says "GPLv2 or later" ✓
- Need to verify all third-party dependencies are GPL-compatible

**GPL Obligations:**
- If you redistribute modified plugin, must release under GPL
- Only applies if you distribute - internal use doesn't require sharing
- Users can modify and redistribute freely under GPL terms

**Tasks:**
- [x] Plugin header declares GPLv2 or later license
- [ ] Add LICENSE file to plugin root (full GPL text)
- [ ] Review all third-party code/libraries for GPL compatibility
- [ ] Document license of any bundled assets (images, fonts, etc.)
- [ ] Add license notice to main plugin file header
- [ ] Verify no proprietary code is included

### 5.2 License File

**Add to Plugin Root:**
```
inat-observations-wp/
├── LICENSE                  # Full GPL v2 license text
├── inat-observations-wp.php
├── readme.txt
└── ...
```

**Tasks:**
- [ ] Download GPL v2 license text from https://www.gnu.org/licenses/gpl-2.0.txt
- [ ] Create LICENSE file in plugin root
- [ ] Add copyright notice to LICENSE file
- [ ] Reference LICENSE file in readme.txt

---

## 6. Plugin Metadata and Requirements

### 6.1 WordPress and PHP Version Requirements

**Current Plugin:**
- No minimum WordPress version specified in code (only readme.txt)
- No PHP version check in code
- Version 0.1.0 uses modern PHP features (closures, namespaces?)

**Recommended Requirements:**
- **WordPress**: 5.0+ (released December 2018, reasonable baseline)
- **PHP**: 7.4+ (minimum for modern PHP features, EOL but widely supported)
  - Consider PHP 8.0+ for new development
- **Tested up to**: WordPress 6.5 (latest as of early 2026)

**Version Checking:**
```php
// Add to main plugin file after header
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo esc_html__('iNaturalist Observations requires PHP 7.4 or higher.', 'inat-observations-wp');
        echo '</p></div>';
    });
    return; // Don't load plugin
}

if (version_compare($GLOBALS['wp_version'], '5.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo esc_html__('iNaturalist Observations requires WordPress 5.0 or higher.', 'inat-observations-wp');
        echo '</p></div>';
    });
    return; // Don't load plugin
}
```

**Tasks:**
- [ ] Determine minimum WordPress version (recommend 5.0)
- [ ] Determine minimum PHP version (recommend 7.4)
- [ ] Add version checks to main plugin file
- [ ] Test on minimum versions
- [ ] Test on latest WordPress version
- [ ] Update readme.txt with "Tested up to" field
- [ ] Update readme.txt with "Requires PHP" field
- [ ] Update readme.txt with "Requires at least" field

### 6.2 Plugin Tags and Categorization

**Current Tags**: (not yet defined)

**Recommended Tags** (max 5 for WordPress.org):
- inaturalist
- observations
- biodiversity
- nature
- wildlife
- citizen-science (alternative)
- taxonomy (alternative)
- species (alternative)

**Tag Strategy:**
- Choose 5 most relevant search terms
- Balance specific (inaturalist) with general (nature)
- Consider what users would search for
- Check existing plugins for tag usage

**Tasks:**
- [ ] Research popular tags in similar plugins
- [ ] Select 5 tags for WordPress.org submission
- [ ] Add tags to readme.txt header
- [ ] Verify tags follow WordPress.org guidelines

---

## 7. Pre-Release Checklist

### 7.1 Code Quality and Security

**Security Review:**
- [ ] All user inputs sanitized (text inputs, URLs, etc.)
- [ ] All outputs escaped (HTML, attributes, JavaScript)
- [ ] Nonce verification for form submissions
- [ ] Capability checks for admin actions
- [ ] SQL queries use prepared statements (if any direct queries)
- [ ] API credentials stored securely (not in source code)
- [ ] File upload validation (if applicable)
- [ ] CSRF protection on all forms
- [ ] XSS prevention on all outputs

**Code Quality:**
- [ ] No PHP warnings or errors
- [ ] No JavaScript console errors
- [ ] Follows WordPress Coding Standards (WPCS)
- [ ] All functions/classes have DocBlocks
- [ ] Internationalization (i18n) ready
- [ ] No hardcoded text strings (use __(), _e(), etc.)
- [ ] Proper error handling
- [ ] Meaningful variable and function names

**Testing:**
- [ ] Unit tests pass (if implemented)
- [ ] Manual testing on WordPress 5.0 (minimum version)
- [ ] Manual testing on WordPress 6.5 (latest version)
- [ ] Test with PHP 7.4 (minimum version)
- [ ] Test with PHP 8.2+ (latest version)
- [ ] Test on multisite installation (if claiming compatibility)
- [ ] Test plugin activation/deactivation
- [ ] Test plugin uninstall (cleanup)
- [ ] Test with popular themes (Twenty Twenty-Three, etc.)
- [ ] Test with popular plugins (Gutenberg, WooCommerce, etc.)
- [ ] Test on different browsers (Chrome, Firefox, Safari, Edge)
- [ ] Mobile responsiveness testing

**Performance:**
- [ ] Database queries optimized
- [ ] Caching implemented for API calls
- [ ] No N+1 query problems
- [ ] Efficient data structures
- [ ] Minimal external HTTP requests
- [ ] Lazy loading where appropriate

**Accessibility:**
- [ ] Keyboard navigation works
- [ ] Screen reader friendly
- [ ] ARIA labels where needed
- [ ] Color contrast meets WCAG standards
- [ ] Form labels properly associated

**Documentation:**
- [ ] readme.txt complete and accurate
- [ ] Inline code comments for complex logic
- [ ] API/filter/action hooks documented
- [ ] Shortcode usage documented
- [ ] FAQ section covers common issues
- [ ] Installation instructions clear

### 7.2 WordPress.org Specific Requirements

**Plugin Guidelines Compliance:**
- [ ] Plugin does one thing well (focused purpose)
- [ ] No "plugin detector" or "plugin recommendation" functionality
- [ ] No external service dependencies without disclosure
- [ ] No unauthorized data collection
- [ ] No phoning home without user consent
- [ ] No trademark/copyright violations
- [ ] Respectful of other plugins (no deactivation of competitors)
- [ ] No obfuscated code
- [ ] No affiliate links in plugin (allowed in readme.txt under specific rules)

**Review Preparation:**
- [ ] Clean, well-commented code
- [ ] Follows WordPress coding standards
- [ ] No security vulnerabilities
- [ ] No spam or misleading content
- [ ] Proper attribution for third-party code
- [ ] LICENSE file included
- [ ] readme.txt follows template

### 7.3 Asset Preparation

**Visual Assets:**
- [ ] Plugin icon designed (128x128 and 256x256)
- [ ] Plugin banner designed (772x250 and 1544x500)
- [ ] Screenshots taken and captioned
- [ ] All images optimized for file size
- [ ] All filenames lowercase
- [ ] All images correct dimensions

**Documentation Assets:**
- [ ] readme.txt complete
- [ ] README.md updated for GitHub
- [ ] CHANGELOG.md created (for GitHub)
- [ ] LICENSE file added
- [ ] Code of Conduct (optional, for GitHub)
- [ ] Contributing guidelines (optional, for GitHub)

---

## 8. Distribution Path Recommendations

### 8.1 For inat-observations-wp Plugin

**Recommended Path**: Hybrid (GitHub + WordPress.org)

**Rationale:**
1. **Target Audience**: WordPress site owners interested in nature/biodiversity
   - Likely non-technical users who expect one-click install
   - Won't manually download and upload ZIP files
   - Expect automatic updates

2. **Use Case**: Public-facing, open-source tool
   - No premium features or pricing model
   - Benefits from wide distribution
   - Educational/scientific purpose aligns with open source

3. **Maintenance**: Automated deployment simplifies maintenance
   - Develop on GitHub (familiar Git workflow)
   - Auto-deploy to WordPress.org on tag push
   - Single source of truth, no manual SVN commits

4. **Discovery**: WordPress.org provides built-in discovery
   - Appears in WordPress admin plugin search
   - Listed on WordPress.org plugin directory
   - SEO benefits for plugin page
   - User reviews and ratings build trust

**Implementation Timeline:**

**Phase 1: GitHub Foundation (Current)**
- ✓ Plugin code on GitHub
- ✓ Development workflow established
- ✓ Version 0.1.0 ready

**Phase 2: Pre-Submission Preparation (1-2 weeks)**
- [ ] Create readme.txt with all sections
- [ ] Add version checks to main plugin file
- [ ] Create visual assets (icon, banner, screenshots)
- [ ] Security audit and code review
- [ ] Testing on multiple WordPress/PHP versions
- [ ] Create LICENSE file
- [ ] Update plugin header comments

**Phase 3: GitHub Release (1 week)**
- [ ] Tag version 0.1.0 in Git
- [ ] Create GitHub release with notes
- [ ] Document manual installation process
- [ ] Beta testing with small user group
- [ ] Gather feedback and fix critical issues

**Phase 4: WordPress.org Submission (2-3 weeks)**
- [ ] Submit plugin to WordPress.org
- [ ] Respond to review feedback
- [ ] Receive SVN access
- [ ] Upload plugin to SVN trunk
- [ ] Add assets to SVN assets directory
- [ ] Create tag 0.1.0 in SVN
- [ ] Verify plugin appears on WordPress.org

**Phase 5: Automation (1 week)**
- [ ] Set up GitHub Action for auto-deployment
- [ ] Create .distignore file
- [ ] Add SVN credentials to GitHub Secrets
- [ ] Test automated deployment with dry-run
- [ ] Tag version 0.1.1 to test workflow
- [ ] Verify auto-deployment works

**Phase 6: Ongoing Maintenance**
- [ ] Tag releases in Git → auto-deploy to WordPress.org
- [ ] Monitor WordPress.org support forum
- [ ] Respond to user reviews
- [ ] Update "Tested up to" field with new WordPress versions
- [ ] Maintain changelog in readme.txt

### 8.2 Alternative: GitHub-Only Distribution

**When to Use:**
- Plugin is experimental or beta-quality
- Target audience is technical (developers, power users)
- Want to maintain tight control over distribution
- Not ready for public review process
- Premium or private plugin

**Current Status**: Not recommended for inat-observations-wp
- Plugin appears production-ready (has caching, error handling, etc.)
- Target audience (WordPress site owners) expects easy installation
- Open-source nature aligns with WordPress.org

**If Choosing GitHub-Only:**
- [ ] Implement self-hosted update mechanism
- [ ] Document manual installation clearly
- [ ] Create detailed troubleshooting guide
- [ ] Provide ZIP downloads on GitHub Releases page
- [ ] Market plugin through other channels (blog, social media, etc.)

---

## 9. Minimal Path to Allow Users to Install

### 9.1 Immediate Option: Manual Installation from GitHub

**User Steps:**
1. Download latest release ZIP from GitHub
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Choose downloaded ZIP file
4. Click "Install Now"
5. Click "Activate Plugin"

**Plugin Requirements:**
- [x] Plugin ZIP structured correctly (root contains main .php file)
- [ ] Clear installation instructions in README.md
- [ ] GitHub release with downloadable ZIP file

**Effort**: Low (1-2 hours)
**User Friction**: High (manual download and upload required)

**Tasks:**
- [ ] Update README.md with manual installation instructions
- [ ] Create first GitHub release (v0.1.0)
- [ ] Test ZIP file installation on clean WordPress site
- [ ] Add "How to Install" section to README.md

### 9.2 Better Option: OneClick Installer Plugin

**Concept**: Users can install via remote ZIP URL using OneClick Installer plugin

**User Steps:**
1. Install OneClick Installer from WordPress.org
2. Copy GitHub release ZIP URL
3. Paste URL into OneClick Installer
4. Click "GO"
5. Plugin installed and activated

**Plugin Requirements:**
- [ ] Direct link to ZIP file on GitHub Releases
- [ ] ZIP file publicly accessible (no authentication)

**Effort**: Low (1 hour)
**User Friction**: Medium (requires installing OneClick Installer first)

**Tasks:**
- [ ] Create GitHub release with ZIP asset
- [ ] Get direct download URL for ZIP file
- [ ] Document OneClick Installer process in README.md
- [ ] Test installation via OneClick Installer

### 9.3 Best Option: WordPress.org Submission

**User Steps:**
1. WordPress Admin → Plugins → Add New
2. Search "iNaturalist Observations"
3. Click "Install Now"
4. Click "Activate"

**Plugin Requirements:**
- [ ] All WordPress.org submission requirements met (see sections above)
- [ ] Approval from WordPress.org plugin team

**Effort**: High (2-4 weeks including review time)
**User Friction**: Minimal (one-click install, built-in updates)

**Tasks:**
- [ ] Complete all Phase 2 tasks (Pre-Submission Preparation)
- [ ] Submit to WordPress.org
- [ ] Pass review process
- [ ] Upload to SVN repository

---

## 10. Recommended Path for Wider Adoption

### 10.1 WordPress.org Directory (Essential)

**Why Essential:**
- **Discovery**: 60,000+ plugins on WordPress.org, massive user base searching daily
- **Trust**: Official WordPress.org listing builds credibility and trust
- **Updates**: Built-in update mechanism means users stay current
- **Support**: Support forum integration for user questions
- **Stats**: Download/install statistics for growth tracking
- **SEO**: WordPress.org plugin pages rank well in search engines

**Adoption Metrics:**
- Track active installations
- Monitor download counts
- Read user reviews and ratings
- Respond to support threads

**Tasks:**
- [ ] Submit to WordPress.org (see Phase 4 above)
- [ ] Monitor support forum daily
- [ ] Respond to reviews (both positive and negative)
- [ ] Collect testimonials from satisfied users
- [ ] Engage with WordPress community

### 10.2 Complementary Strategies

**Documentation and Marketing:**
- [ ] Create dedicated plugin website or landing page
- [ ] Write blog post announcing plugin release
- [ ] Create tutorial videos (YouTube)
- [ ] Write how-to guides for common use cases
- [ ] Submit to WordPress news sites (WP Tavern, etc.)

**Community Engagement:**
- [ ] Share in iNaturalist community forums
- [ ] Post in biodiversity/nature WordPress groups
- [ ] Engage on Twitter/Mastodon with #WordPress #iNaturalist hashtags
- [ ] Present at WordPress meetups or WordCamps
- [ ] Create demo site showcasing plugin features

**SEO and Content:**
- [ ] Optimize plugin description for search terms
- [ ] Create FAQ page on plugin website
- [ ] Write case studies of plugin usage
- [ ] Guest post on WordPress/nature blogs
- [ ] Add structured data markup to plugin pages

**Technical Outreach:**
- [ ] List on GitHub Awesome lists (if applicable)
- [ ] Share in WordPress developer communities
- [ ] Contribute to WordPress core/ecosystem (builds credibility)
- [ ] Offer to collaborate with related plugins

**User Success:**
- [ ] Create onboarding wizard for first-time users
- [ ] Provide default configuration templates
- [ ] Offer email support for setup questions
- [ ] Build library of sample shortcode examples
- [ ] Create video tutorials for common tasks

---

## 11. Example Workflows from Popular Plugins

### 11.1 Yoast SEO (Yoast/wordpress-seo)

**Distribution Model**: WordPress.org + GitHub (Premium version separate)

**GitHub Repository**: https://github.com/Yoast/wordpress-seo
- Public development on GitHub
- Detailed readme.txt in trunk/
- Regular releases with comprehensive changelogs
- Uses semantic versioning (e.g., 21.7, 21.8)
- Active community contributions via pull requests

**Release Process:**
- Development on GitHub main branch
- Tagged releases (e.g., 21.7, 21.8, 22.0)
- Detailed release notes for each version
- Automatic deployment to WordPress.org

**Key Takeaways:**
- Professional readme.txt with extensive documentation
- Clear changelog with "Added", "Fixed", "Other" categories
- Regular updates (every few weeks)
- Responsive to user feedback and issues

### 11.2 Jetpack (Automattic/jetpack)

**Distribution Model**: WordPress.org + GitHub + Jetpack website

**GitHub Repository**: https://github.com/Automattic/jetpack
- Monorepo with multiple packages
- Production mirror at github.com/Automattic/jetpack-production
- Detailed release notes for each version
- Uses GitHub Releases extensively

**Release Process:**
- Development in main Jetpack repository
- Production builds deployed to jetpack-production repository
- Synced to WordPress.org SVN
- Regular release cycle (monthly)

**Key Takeaways:**
- Separation of development and production repositories
- Extensive CI/CD pipeline
- Clear documentation for contributors
- Professional support infrastructure

### 11.3 Common Patterns from Popular Plugins

**Shared Practices:**
1. **Git → SVN automation**: All use GitHub Actions or similar for deployment
2. **Semantic versioning**: Consistent use of MAJOR.MINOR.PATCH format
3. **Detailed changelogs**: Every release has comprehensive notes
4. **Professional assets**: High-quality icons, banners, screenshots
5. **Active maintenance**: Regular updates, security patches, compatibility updates
6. **Community engagement**: Support forums, GitHub issues, documentation
7. **Clear licensing**: GPL v2+ explicitly stated
8. **Internationalization**: Translation-ready from day one

**Release Frequency:**
- Major plugins: Every 2-4 weeks
- Security updates: As needed (immediate)
- Compatibility updates: With each new WordPress version

**Support Strategy:**
- WordPress.org support forum for free version
- GitHub Issues for bug reports and feature requests
- Separate premium support for paid versions (if applicable)

---

## 12. Action Items Summary

### Immediate (Week 1-2): Pre-Release Preparation

**Must Have:**
- [ ] Create readme.txt with all required sections
- [ ] Add version checks to main plugin file
- [ ] Security audit (sanitize inputs, escape outputs, nonces)
- [ ] Create LICENSE file (GPL v2)
- [ ] Test on WordPress 5.0 and 6.5
- [ ] Test on PHP 7.4 and 8.2

**Should Have:**
- [ ] Design plugin icon (128x128, 256x256)
- [ ] Design plugin banner (772x250, 1544x500)
- [ ] Take 3-4 screenshots with captions
- [ ] Create comprehensive FAQ section
- [ ] Write installation instructions

**Nice to Have:**
- [ ] Create demo video
- [ ] Build demo website showing plugin in action
- [ ] Write blog post for launch

### Short Term (Week 3-4): Initial Release

**GitHub Release:**
- [ ] Tag version 0.1.0 in Git
- [ ] Create GitHub release with detailed notes
- [ ] Update README.md with installation instructions
- [ ] Create CHANGELOG.md for GitHub

**WordPress.org Submission:**
- [ ] Submit plugin to WordPress.org
- [ ] Respond to review feedback promptly
- [ ] Make any required changes
- [ ] Await approval (5-14 days)

### Medium Term (Month 2): Production Deployment

**WordPress.org Setup:**
- [ ] Receive SVN access credentials
- [ ] Check out SVN repository
- [ ] Upload plugin files to trunk/
- [ ] Upload assets to assets/
- [ ] Create tag 0.1.0 in SVN
- [ ] Verify plugin appears on WordPress.org

**Automation:**
- [ ] Set up GitHub Action for deployment
- [ ] Create .distignore file
- [ ] Test automated deployment workflow
- [ ] Document deployment process

### Long Term (Ongoing): Maintenance and Growth

**Regular Maintenance:**
- [ ] Monitor WordPress.org support forum (weekly)
- [ ] Respond to user reviews (weekly)
- [ ] Test with new WordPress versions (when released)
- [ ] Update "Tested up to" field (with each WP release)
- [ ] Security updates (as needed, immediate)
- [ ] Feature updates (monthly or quarterly)

**Community Building:**
- [ ] Write tutorials and how-to guides
- [ ] Engage with iNaturalist community
- [ ] Present at WordPress meetups
- [ ] Collect user testimonials
- [ ] Track plugin statistics and metrics

---

## 13. Questions to Resolve

### 13.1 Plugin Naming

**Current Name**: inat-observations-wp
**Display Name Options:**
- "iNaturalist Observations"
- "iNat Observations for WordPress"
- "Biodiversity Observations (iNaturalist)"

**Question**: Should we rename the plugin for better discoverability?
**Recommendation**: Use "iNaturalist Observations" as display name (Plugin Name header), keep "inat-observations-wp" as slug/text-domain

### 13.2 Target WordPress Version

**Question**: What's the minimum WordPress version to support?
**Options:**
- 5.0 (December 2018) - Gutenberg editor introduced
- 5.9 (January 2022) - Full Site Editing
- 6.0 (May 2022) - Recent stable baseline

**Recommendation**: WordPress 5.0 (broad compatibility, not using FSE features)

### 13.3 Target PHP Version

**Question**: What's the minimum PHP version to support?
**Options:**
- 7.4 (EOL November 2022, but still widely used)
- 8.0 (November 2020, more modern)
- 8.1 (November 2021, latest widely available)

**Recommendation**: PHP 7.4 (balance between modern features and compatibility)

### 13.4 Premium Features

**Question**: Are there plans for premium/pro version?
**Current**: Appears to be fully open-source, no premium features identified
**Recommendation**: Keep fully free/open-source for initial launch, evaluate later based on user feedback

---

## 14. Resources and References

### Official WordPress Documentation
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Plugin Submission Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin Readme Guide](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Using Subversion (SVN)](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/)
- [Plugin Assets Guide](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)

### WordPress.org Links
- [Add Your Plugin](https://wordpress.org/plugins/developers/add/)
- [Developer Resources](https://wordpress.org/plugins/developers/)
- [Readme Validator](https://wordpress.org/plugins/readme.txt)

### Licensing
- [WordPress License](https://wordpress.org/about/license/)
- [GPL v2 License Text](https://www.gnu.org/licenses/gpl-2.0.html)
- [GPL Compatibility Guide](https://developer.wordpress.org/plugins/plugin-basics/including-a-software-license/)

### Tools and Libraries
- [10up WordPress Plugin Deploy Action](https://github.com/10up/action-wordpress-plugin-deploy)
- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
- [GitHub Updater](https://github.com/afragen/github-updater)
- [OneClick Installer](https://wordpress.org/plugins/search/oneclick/)

### Best Practices
- [Semantic Versioning](https://semver.org/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [Internationalization Guide](https://developer.wordpress.org/plugins/internationalization/)

### Community Resources
- [WP Tavern](https://wptavern.com/) - WordPress news and updates
- [Make WordPress Plugins](https://make.wordpress.org/plugins/) - Plugin team blog
- [WordPress.org Support Forums](https://wordpress.org/support/forums/)

### Articles Referenced in Research
- [How to Submit Plugins to WordPress.org Repository](https://wpexperts.io/blog/submit-plugins-to-wordpress-repository/)
- [Planning, Submitting, and Maintaining Plugins](https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/)
- [The WordPress Ecosystem is Growing (2025 Update)](https://make.wordpress.org/plugins/2025/05/21/the-wordpress-ecosystem-is-growing-new-plugin-submissions-have-doubled-in-2025/)
- [How to Use SVN for WordPress Plugin Development](https://wpfitter.com/blog/how-to-use-svn-for-wordpress-plugin-development/)
- [Publishing Your First WordPress Plugin with GIT and SVN](https://learnwithdaniel.com/2019/09/publishing-your-first-wordpress-plugin-with-git-and-svn/)
- [Self-Host WordPress Plugins on GitHub](https://eduardovillao.me/how-to-self-host-wordpress-plugins-on-github-and-deliver-updates/)
- [Semantic Versioning for WordPress](https://sternerstuff.dev/2021/02/semantic-versioning-wordpress/)
- [SemVer for WordPress Plugins](https://salferrarello.com/semver-wordpress-plugins/)
- [WordPress and GPL - Everything You Need to Know](https://kinsta.com/learn/wordpress-gpl/)
- [How Your Plugin Assets Work](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [The Definitive Guide to WordPress.org Plugin Assets Folder](https://artiss.blog/2024/04/the-definitive-guide-to-the-wordpress-org-plugin-assets-folder/)

---

## 15. Conclusion

**Summary**: The iNat Observations plugin is ready for public release with some preparation work. The recommended path is hybrid distribution (GitHub + WordPress.org) for maximum reach and user convenience.

**Timeline Estimate:**
- Pre-release preparation: 1-2 weeks
- WordPress.org submission and approval: 2-3 weeks
- Automation setup: 1 week
- **Total to public release**: 4-6 weeks

**Next Steps:**
1. Review this document and decide on distribution strategy
2. Complete pre-release preparation checklist (Section 7.1)
3. Create visual assets (icon, banner, screenshots)
4. Submit to WordPress.org
5. Set up automated deployment workflow

**Success Metrics:**
- Plugin approved on WordPress.org
- 100+ active installations in first month
- 4+ star average rating
- Active support forum with timely responses
- Regular updates and maintenance

---

**Document Metadata:**
- Created: 2026-01-06
- Last Updated: 2026-01-06
- Author: Research compiled by Claude (Anthropic)
- Status: Complete - Ready for Review
- Next Review: Before WordPress.org submission
