# TODO-002: Add Pagination to Grid and List Views

**Created:** 2026-01-07
**Status:** 🔨 In Progress
**Priority:** HIGH
**Target:** 0.2.0 Release

---

## Overview

Replace simple "Showing 1-50 of 2000+" text with full pagination bar including page numbers, navigation buttons, and responsive behavior for small screens.

---

## Current UI

```
[ Grid | List ] Show [50]        Showing 1-50 of 2000+
```

---

## Target UI

### Desktop (Full Width)
```
[ Grid | List ] #### Observations [ 50 per page ] [First Page] [Prev Page] 1 2 3 ... 8 9 [Next Page] [Last Page]
```

### Tablet (Medium Width)
```
[ Grid | List ] #### Observations [ 50 per page ] [Prev] 1 2 3 ... 9 [Next]
```

### Mobile (Small Width)
```
[ Grid | List ]
#### Observations [ 50 per page ]
[‹] 1 2 3 [›]
```

---

## Design Requirements

### Components

1. **View Switcher** `[ Grid | List ]`
   - Remains unchanged
   - Always visible

2. **Count Display** `#### Observations`
   - Shows total filtered count
   - Updates when filters change
   - Format: "2,345 Observations" (with commas)

3. **Per Page Selector** `[ 50 per page ]`
   - Dropdown: 10, 25, 50, 100
   - Saves preference to localStorage
   - Triggers reload on change

4. **First Page Button** `[First Page]`
   - Disabled on page 1
   - Hidden on mobile (<600px)

5. **Previous Page Button** `[Prev Page]` / `[Prev]` / `[‹]`
   - Disabled on page 1
   - Full text on desktop
   - Short text on tablet
   - Arrow only on mobile

6. **Page Numbers** `1 2 3 ... 8 9`
   - Current page highlighted
   - Always show first page
   - Always show last page
   - Show ellipsis (...) for gaps
   - Desktop: 7 visible numbers max
   - Tablet: 5 visible numbers max
   - Mobile: 3 visible numbers max

7. **Next Page Button** `[Next Page]` / `[Next]` / `[›]`
   - Disabled on last page
   - Full text on desktop
   - Short text on tablet
   - Arrow only on mobile

8. **Last Page Button** `[Last Page]`
   - Disabled on last page
   - Hidden on mobile (<600px)

---

## Responsive Breakpoints

| Screen Size | Components | Page Numbers | Button Text |
|-------------|------------|--------------|-------------|
| **Desktop** (≥992px) | All | 7 max | Full ("First Page") |
| **Tablet** (600-991px) | No First/Last | 5 max | Short ("Prev") |
| **Mobile** (<600px) | No First/Last | 3 max | Arrows ("‹") |

---

## Material Design Guidelines

### Colors
- Active page: Primary color (#4CAF50)
- Hover: Primary light (#81C784)
- Disabled: Grey (#BDBDBD)
- Background: White (#FFFFFF)
- Border: Light grey (#E0E0E0)

### Spacing
- Button padding: 8px 16px (desktop), 8px 12px (tablet), 8px (mobile)
- Gap between buttons: 8px
- Margin top: 24px
- Margin bottom: 24px

### Typography
- Font size: 14px
- Font weight: 500 (medium)
- Line height: 1.5

### Elevation
- Buttons: 1dp (subtle shadow)
- Hover: 2dp (raised shadow)
- Active page: 3dp (emphasized shadow)

### Accessibility
- Focus visible: 2px solid primary
- ARIA labels on all buttons
- Keyboard navigation support
- Screen reader announcements

---

## Implementation Tasks

### 1. UI Components ✅
- [ ] Create pagination bar container
- [ ] Add count display with formatting
- [ ] Add per-page selector dropdown
- [ ] Add First/Prev/Next/Last buttons
- [ ] Add page number buttons with ellipsis
- [ ] Add responsive CSS for breakpoints
- [ ] Add Material Design styles (elevation, colors)

### 2. JavaScript Logic 🎯
- [ ] Calculate total pages from API response
- [ ] Generate page number array with ellipsis
- [ ] Handle page button clicks
- [ ] Handle per-page selector change
- [ ] Update URL with page parameter
- [ ] Preserve filters when changing pages
- [ ] Save per-page preference to localStorage
- [ ] Disable/enable buttons based on current page

### 3. API Integration 🔌
- [ ] Ensure REST API returns total count
- [ ] Ensure REST API returns total_pages
- [ ] Update fetch logic to pass page parameter
- [ ] Update fetch logic to pass per_page parameter
- [ ] Handle filtered count updates

### 4. Responsive Behavior 📱
- [ ] Hide First/Last on mobile (<600px)
- [ ] Shorten button text on tablet (600-991px)
- [ ] Use arrow icons on mobile
- [ ] Reduce visible page numbers on small screens
- [ ] Stack components vertically on mobile if needed

### 5. Accessibility ♿
- [ ] Add ARIA labels to all buttons
- [ ] Add role="navigation" to pagination
- [ ] Add aria-current="page" to current page
- [ ] Add aria-disabled to disabled buttons
- [ ] Ensure keyboard navigation works (Tab, Enter, Space)
- [ ] Add screen reader announcements for page changes

---

## Testing Tasks

### Unit Tests
- [ ] Test page number generation (with ellipsis)
- [ ] Test page calculation (total / per_page)
- [ ] Test button disable logic
- [ ] Test per-page preference storage

### Integration Tests
- [ ] Test pagination with 10 observations (1 page)
- [ ] Test pagination with 150 observations (multiple pages)
- [ ] Test pagination with filters (updates count)
- [ ] Test page navigation (prev/next/first/last)
- [ ] Test per-page change triggers reload
- [ ] Test URL updates with page parameter
- [ ] Test responsive behavior (mobile/tablet/desktop)

---

## CSS Classes

```css
.inat-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 24px;
  margin: 24px 0;
  flex-wrap: wrap;
}

.inat-pagination__count {
  font-size: 16px;
  font-weight: 500;
  color: #333;
}

.inat-pagination__per-page {
  display: flex;
  align-items: center;
  gap: 8px;
}

.inat-pagination__controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.inat-pagination__button {
  padding: 8px 16px;
  border: 1px solid #e0e0e0;
  background: white;
  color: #333;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border-radius: 4px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.1);
  transition: all 0.2s ease;
}

.inat-pagination__button:hover:not(:disabled) {
  background: #f5f5f5;
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}

.inat-pagination__button:disabled {
  color: #bdbdbd;
  cursor: not-allowed;
  opacity: 0.5;
}

.inat-pagination__button--active {
  background: #4CAF50;
  color: white;
  border-color: #4CAF50;
  box-shadow: 0 2px 6px rgba(76,175,80,0.3);
}

.inat-pagination__ellipsis {
  padding: 8px;
  color: #999;
}

/* Responsive */
@media (max-width: 991px) {
  .inat-pagination__button--first,
  .inat-pagination__button--last {
    display: none;
  }

  .inat-pagination__button {
    padding: 8px 12px;
  }
}

@media (max-width: 599px) {
  .inat-pagination {
    gap: 12px;
  }

  .inat-pagination__button {
    padding: 8px;
    min-width: 40px;
  }

  .inat-pagination__button .text {
    display: none;
  }

  .inat-pagination__button .icon {
    display: inline;
  }
}
```

---

## Success Criteria

- [ ] Pagination bar displays correctly on all screen sizes
- [ ] Page navigation works (first, prev, next, last)
- [ ] Page numbers display correctly with ellipsis
- [ ] Current page is highlighted
- [ ] Per-page selector changes results
- [ ] Filtered counts update correctly
- [ ] URL updates with page parameter
- [ ] Responsive behavior works (mobile/tablet/desktop)
- [ ] Accessibility requirements met (ARIA, keyboard nav)
- [ ] Material Design guidelines followed
- [ ] All integration tests pass

---

## Dependencies

- REST API pagination metadata (total, total_pages)
- localStorage for per-page preference
- CSS media queries for responsive behavior
- ARIA attributes for accessibility

---

## Notes

- Current simple text: "Showing 1-50 of 2000+"
- Target: Full pagination bar with Material Design
- Must be responsive to small screens
- Must follow accessibility best practices
- Must update filtered counts correctly

---

## Related Tasks

- Ensure filtered counts work correctly
- Test with TODO-QA-001 integration tests
- Update REST API to return pagination metadata (already done)
