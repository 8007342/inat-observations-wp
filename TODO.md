# TODO - iNaturalist Observations WordPress Plugin

**Last Updated**: 2026-01-06
**Current State**: ⏸️ **CHECKPOINT - Awaiting Docker Setup on Host**

---

## 🎯 Current Checkpoint

### ✅ Completed Today

1. **inat.sh Bootstrap Script**
   - Created `inat.sh` (entry point for development)
   - Auto-creates "inat-observations" toolbox
   - Installs Docker CLI, PHP, Composer, WP-CLI, MySQL client
   - Fixed SELinux permissions (`:Z` volume flags)
   - Updated `docker-compose.yml` for Silverblue compatibility

2. **Admin Settings Page**
   - Complete WordPress settings page implementation
   - User ID and Project ID inputs (at least one required)
   - Pre-seeded with San Diego Mycological Society project (`sdmyco`)
   - Manual "Refresh Now" button (AJAX)
   - Status display (last refresh timestamp, observation count)
   - File: `includes/admin.php` (224 lines, complete)

3. **WP-Cron Refresh Job**
   - Fetches observations from iNaturalist API
   - Stores in database
   - Updates last refresh stats
   - File: `includes/init.php:33-69`

4. **API Integration Updates**
   - Supports both `user_id` and `project_id` parameters
   - Flexible query building
   - File: `includes/api.php:11-39`

5. **Documentation**
   - `TODO-001-filter-dna-observations.md` - DNA research plan
   - `TODO-002-phase-1-implementation.md` - Implementation roadmap
   - `TODO-003-xss-investigation.md` - Security research
   - `TODO-004-inat-sh.md` - Script troubleshooting
   - `WORDPRESS-PLUGIN.md` - Complete architecture
   - `IMPLEMENTATION-SUMMARY.md` - Testing guide
   - `SETUP-HOST.md` - Docker host setup instructions

---

## 🔴 Blocker: Docker Setup on Host Required

### Issue
Script crashes because Docker daemon not running on host.

### What You Need to Do (After Reboot)

**Option 1: Full Setup (Recommended)**
```bash
# On HOST (not in toolbox)
sudo rpm-ostree install docker docker-compose
sudo systemctl reboot

# After reboot
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker ps
```

**Option 2: If Docker Already Installed**
```bash
# On HOST
sudo systemctl start docker
sudo systemctl enable docker
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker ps
```

**See**: `SETUP-HOST.md` for detailed instructions

---

## ⏭️ Next Steps (After Docker Setup)

### 1. Test inat.sh Script

```bash
cd ~/src/inat-observations-wp
./inat.sh
```

**Expected**:
- Toolbox "inat-observations" detected or created
- Dependencies installed
- WordPress + MySQL start
- Logs stream to terminal
- Available at http://localhost:8080

**If Issues**: Check `TODO-004-inat-sh.md` troubleshooting section

---

### 2. Activate Plugin in WordPress

1. Visit http://localhost:8080/wp-admin
2. Complete WordPress setup (first time)
   - Site title: "iNat Observations Dev"
   - Username: admin
   - Password: admin
3. Go to Plugins → Installed Plugins
4. Activate "inat-observations-wp"

**Expected**:
- Database table `wp_inat_observations` created
- WP-Cron job scheduled (daily refresh)

---

### 3. Test Admin Settings Page

1. Settings → iNat Observations
2. Verify **Project ID** pre-filled with `sdmyco`
3. Click "Refresh Now" button

**Expected**:
- AJAX request succeeds
- Success message: "Refresh completed successfully. Fetched N observations."
- Page reloads after 2 seconds
- Status shows updated timestamp and count

---

### 4. Verify Database

```bash
# From another terminal
toolbox enter inat-observations
docker exec -it mysql mysql -u wordpress -pwordpress wordpress

# Inside MySQL
SELECT COUNT(*) FROM wp_inat_observations;
SELECT id, species_guess, place_guess FROM wp_inat_observations LIMIT 5;
```

**Expected**:
- Observations from San Diego Mycological Society
- Fields populated: id, uuid, observed_on, species_guess, place_guess, metadata

---

## 📋 Phase 1 Status

### ✅ Completed (Tasks 1, 4, 6, 7)
- [x] Admin settings page
- [x] WP-Cron refresh job implementation
- [x] API integration updates (user_id + project_id)
- [x] inat.sh bootstrap script

### ⏳ Remaining for Phase 1 Complete
- [ ] **Task 2**: Database schema migration
  - Add DNA columns (`has_dna`, `dna_type`)
  - Add image columns (`image_url`, `thumbnail_url`)
  - Add user/taxon columns
  - Add indexes
  - File: `includes/db-schema.php`

- [ ] **Task 3**: DNA metadata parsing
  - Implement `inat_obs_parse_dna_metadata()` function
  - Research DNA field IDs (TODO-001)
  - Pattern matching fallback
  - File: `includes/api.php` (new function)

- [ ] **Task 5**: Enhanced data storage
  - Update `inat_obs_store_items()` to parse all fields
  - Call DNA parsing function
  - Extract images, taxon, user info
  - File: `includes/db-schema.php:37-60` (update)

---

## 🗂️ Project Structure

```
inat-observations-wp/
├── inat.sh                    # ✅ Entry point (NEW)
├── docker-compose.yml         # ✅ Updated for Silverblue
├── WORDPRESS-PLUGIN.md        # ✅ Architecture
├── TODO.md                    # ✅ This file (NEW)
├── TODO-001-*.md              # ✅ DNA research
├── TODO-002-*.md              # ✅ Phase 1 plan
├── TODO-003-*.md              # ✅ XSS investigation
├── TODO-004-*.md              # ✅ inat.sh docs
├── IMPLEMENTATION-SUMMARY.md  # ✅ Testing guide
├── SETUP-HOST.md              # ✅ Docker setup (NEW)
├── wp-content/plugins/inat-observations-wp/
│   ├── inat-observations-wp.php
│   ├── includes/
│   │   ├── init.php           # ✅ Updated (WP-Cron)
│   │   ├── admin.php          # ✅ Complete rewrite
│   │   ├── api.php            # ✅ Updated (user_id + project_id)
│   │   ├── db-schema.php      # ⏳ Needs migration
│   │   ├── shortcode.php      # ⏳ Needs Phase 2
│   │   └── rest.php
│   └── assets/
│       ├── js/main.js         # ⏳ Needs Phase 2
│       └── css/main.css       # ⏳ Needs Phase 5
└── docker-volumes/            # Created by inat.sh
    ├── mysql/
    └── wordpress/
```

---

## 🎯 Roadmap (6 Phases)

### Phase 1: Admin Settings & Data Pipeline ⏳ (75% complete)
- [x] Admin page
- [x] WP-Cron job
- [x] API integration
- [ ] Database migration
- [ ] DNA parsing
- [ ] Enhanced storage

### Phase 2: Basic Frontend Display (Not Started)
- [ ] Grid view
- [ ] List view
- [ ] Image thumbnails
- [ ] Lazy loading

### Phase 3: Filtering & DNA Detection (Not Started)
- [ ] Filter bar
- [ ] DNA badge
- [ ] Autocomplete

### Phase 4: Details View (Not Started)
- [ ] Full-screen modal
- [ ] Full metadata display

### Phase 5: Material Design Polish (Not Started)
- [ ] Material Design 3
- [ ] Responsive design

### Phase 6: Advanced Features (Not Started)
- [ ] Export to CSV
- [ ] User preferences
- [ ] Custom fields

**See**: `WORDPRESS-PLUGIN.md` for complete roadmap

---

## 🐛 Known Issues

### 1. Docker Daemon Not Running (CURRENT)
**Status**: Blocking development
**Fix**: See "Blocker" section above
**Doc**: `SETUP-HOST.md`

### 2. MySQL Permission Errors (Potential)
**Symptom**: "Cannot change permissions of the file"
**Cause**: SELinux contexts
**Fix**: `:Z` flags in docker-compose.yml (already applied)
**Doc**: `TODO-004-inat-sh.md`

### 3. Database Missing DNA/Image Columns (Expected)
**Status**: Pending implementation
**Impact**: Can't filter by DNA or display images yet
**Fix**: Implement Task 2 (database migration)
**Doc**: `TODO-002-phase-1-implementation.md`

---

## 🔍 Files Modified Today

### Created
- `inat.sh`
- `TODO.md` (this file)
- `TODO-004-inat-sh.md`
- `IMPLEMENTATION-SUMMARY.md`
- `SETUP-HOST.md`

### Modified
- `docker-compose.yml` - SELinux `:Z` flags
- `.gitignore` - Exclude `docker-volumes/`
- `includes/admin.php` - Complete settings page
- `includes/init.php` - WP-Cron implementation
- `includes/api.php` - user_id + project_id support

### Read-Only (Reference)
- `WORDPRESS-PLUGIN.md`
- `TODO-001-filter-dna-observations.md`
- `TODO-002-phase-1-implementation.md`
- `TODO-003-xss-investigation.md`

---

## 💡 Quick Commands

```bash
# Start development
./inat.sh

# Stop containers
./inat.sh --stop

# Clean everything (DESTRUCTIVE)
./inat.sh --clean

# Check Docker on host (outside toolbox)
sudo systemctl status docker

# View logs
docker logs -f wordpress
docker logs -f mysql

# Access MySQL
docker exec -it mysql mysql -u wordpress -pwordpress wordpress

# Enter toolbox manually
toolbox enter inat-observations
```

---

## 📚 Documentation Reference

| File | Purpose |
|------|---------|
| `WORDPRESS-PLUGIN.md` | Complete architecture and 6-phase roadmap |
| `TODO-002-phase-1-implementation.md` | Phase 1 detailed plan |
| `IMPLEMENTATION-SUMMARY.md` | Testing instructions |
| `SETUP-HOST.md` | Docker setup on Silverblue host |
| `TODO-004-inat-sh.md` | inat.sh troubleshooting |

---

## 🎉 What's Working

- ✅ Bootstrap script (inat.sh) creates toolbox
- ✅ Docker Compose configuration (Silverblue-compatible)
- ✅ Admin settings page (fully functional)
- ✅ WP-Cron job (fetches and stores observations)
- ✅ API integration (supports user_id and project_id)
- ✅ San Diego Mycological Society pre-seeded

## 🚧 What's Next

1. **Immediate**: Set up Docker on host, test inat.sh
2. **Phase 1**: Database migration, DNA parsing
3. **Phase 2**: Frontend grid/list views

---

**Status**: 🔴 Blocked on Docker host setup
**Next Action**: Follow `SETUP-HOST.md` instructions, then run `./inat.sh`
**Owner**: Full-Stack Developer

---

**Notes**:
- Project ID `sdmyco` is San Diego Mycological Society (original inspiration)
- Plugin architecture documented in `WORDPRESS-PLUGIN.md`
- All security best practices followed (nonce, sanitization, HTTPS enforcement)
