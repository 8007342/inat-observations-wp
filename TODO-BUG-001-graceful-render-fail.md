# TODO-BUG-001: Graceful Render Failure Recovery

**Priority:** 🟡 MEDIUM (UX Enhancement)
**Status:** ✅ IMPLEMENTED
**Created:** 2026-01-07

---

## Overview

Implement graceful error recovery for all render failures in the iNaturalist observations plugin. When queries fail (database errors, API errors, bad filter combinations), the UI should **never** break completely. Instead, show a friendly error message with recovery options.

**Philosophy:** HTTP is stateless - leverage this! A simple "Reset Filters" button can recover from almost any broken state by reloading without parameters.

---

## Core Principle: Recoverable State

```
Bad State → User Recovers → Clean State
   ↓              ↓              ↓
Filters    Reset Button    No Filters
  Error   →    Reload    →   Success
```

**No state persistence needed** - just clear client-side filters and fetch again. Tricky simplicity! 🎭

---

## Implementation Status

### ✅ Completed

1. **Empty Results with Filters** (`main.js:96-119`)
   - Detects: `results.length === 0` + active filters
   - Shows: "No observations match your filters"
   - Recovery: "Reset All Filters" button
   - Action: Clears all filters, resets to page 1, fetches clean data

2. **API/Database Errors** (`main.js:452-493`)
   - Catches: Network errors, malformed responses, query failures
   - Shows: "⚠️ Something went wrong"
   - Recovery Options:
     - "Reset Filters" (if filters active)
     - "Refresh Page" (always available)
   - Debug: Collapsible technical details (error message + stack trace)

3. **Empty Database (No Filters)** (`main.js:121-134`)
   - Detects: `results.length === 0` + no filters
   - Shows: Setup instructions
   - Action: Guides user to Settings → Refresh Now

---

## Error States Covered

| Error Type | Detection | Recovery |
|------------|-----------|----------|
| **No results with filters** | `results.length === 0` + filters | Reset filters → fetch unfiltered |
| **Network failure** | Fetch error, timeout | Reset filters or reload page |
| **Database error** | SQL exception, table missing | Reset filters or reload page |
| **Malformed response** | JSON parse error | Reset filters or reload page |
| **Empty database** | `results.length === 0` + no filters | Show setup guide |

---

## Recovery Flow

```javascript
// Unified recovery function
function resetToCleanState() {
  currentFilters.species = [];
  currentFilters.location = [];
  currentFilters.hasDNA = false;
  currentPage = 1;
  fetchObservations();  // Fresh start, no parameters
}
```

**HTTP Stateless Magic:**
- No filters in URL → No cache key → Fresh query
- No corrupted state persisted → Can't stay broken
- User clicks Reset → Instant recovery

---

## UI Patterns

### 1. No Results (Filtered)

```
┌─────────────────────────────────────┐
│  No observations match your filters │
│                                     │
│  Try different search terms or      │
│  remove some filters.               │
│                                     │
│       [Reset All Filters]           │
└─────────────────────────────────────┘
```

### 2. Error Recovery

```
┌─────────────────────────────────────┐
│  ⚠️ Something went wrong            │
│                                     │
│  Unable to load observations.       │
│  This might be a temporary issue.   │
│                                     │
│  [Reset Filters]  [Refresh Page]    │
│                                     │
│  ▸ Technical details                │
└─────────────────────────────────────┘
```

### 3. Empty Database

```
┌─────────────────────────────────────┐
│  No observations found              │
│                                     │
│  1. Go to Settings → iNat Obs       │
│  2. Click "Refresh Now"             │
│  3. Wait for fetch to complete      │
│  4. Return and refresh page         │
└─────────────────────────────────────┘
```

---

## Future Enhancements

### 🔮 Potential Improvements

1. **Retry Logic**
   - Auto-retry failed requests (3 attempts)
   - Exponential backoff (1s, 2s, 4s)
   - Show "Retrying..." indicator

2. **Offline Detection**
   - Check `navigator.onLine`
   - Show "You appear to be offline" message
   - Auto-recover when connection restored

3. **Query Validation**
   - Validate filters before sending
   - Catch impossible combinations (e.g., species + location that can't coexist)
   - Show "Invalid filter combination" with suggestions

4. **Smart Recovery Suggestions**
   - "Remove 'San Diego' location and try again?"
   - "Try broader search terms?"
   - Context-aware hints based on error type

5. **Analytics/Logging**
   - Log errors to server for debugging
   - Track recovery success rates
   - Identify common failure patterns

---

## Testing Checklist

- [x] Empty results with species filter → Shows reset button
- [x] Empty results with location filter → Shows reset button
- [x] Empty results with DNA filter → Shows reset button
- [x] Empty results with multiple filters → Shows reset button
- [x] Network error → Shows error with recovery options
- [x] Reset button clears all filters
- [x] Reset button triggers fresh fetch
- [x] Refresh page button works
- [x] Technical details expand/collapse
- [ ] Malformed JSON response → Graceful error (manual test needed)
- [ ] Database connection failure → Graceful error (manual test needed)
- [ ] SQL syntax error → Graceful error (manual test needed)

---

## Code Locations

**JavaScript:**
- Empty results handler: `assets/js/main.js:92-135`
- Error catch handler: `assets/js/main.js:452-493`
- Reset function (inline): Lines 110-115, 483-488

**PHP:** (Future - backend validation)
- REST endpoint: `includes/rest.php:13-140`
- Error responses: TBD

---

## Design Philosophy

> **"The best error message is the one that helps you recover, not the one that tells you what went wrong."**

- ✅ **User-focused:** "No matches" not "Query returned 0 rows"
- ✅ **Actionable:** Clear recovery path (Reset/Refresh buttons)
- ✅ **Non-technical:** Simple language, details hidden by default
- ✅ **Stateless recovery:** Leverage HTTP to reset to clean state
- ✅ **Mobile-friendly:** Large touch targets, clear hierarchy

---

## Related TODOs

- TODO-003: Filter autocomplete caching
- TODO-005: DNA filter implementation

---

**Status:** ✅ Graceful error recovery implemented and working!
**Next:** Test edge cases (malformed responses, SQL errors)
