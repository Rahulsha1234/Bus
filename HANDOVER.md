# AI Handover Documentation: SwiftBus Booking System
This file serves as a context bridge summarizing all the key modifications, database integrations, security enhancements, and UI upgrades implemented in the SwiftBus booking codebase during the recent session. Read this file to catch up on what was built and edited.

---

## 1. Project Context
- **System Name:** SwiftBus (A Premium Bus Booking System)
- **Tech Stack:** PHP (OOP/PDO), MySQL/MariaDB (Port 3307), jQuery, Bootstrap 5, Vanilla CSS variables.
- **Theme:** Refined cream-based luxury theme (`#F8F5EF` Warm Cream background, `#FFFFFF` Ivory cards, `#C8A96B` Gold accents, and `#1F1F1F` Text). Supported by standard dark/light theme switching.

---

## 2. Key Changes & Fixes Completed

### 🛠️ UI & Styling Enhancements
1. **Light Theme Seating Grid Visibility (`agent/configure_layout.php`)**
   - **Issue:** The seating grid canvas had a hardcoded dark background (`rgba(0,0,0,0.15)`) and faint cell borders, which obscured cells in light mode.
   - **Fix:** Switched the canvas background and cell borders to use dynamic CSS variables (`--grid-canvas-bg` and `--grid-cell-border`) that change contrast depending on whether dark/light theme is active.

2. **Sleeper Seat Grid Clashes (`book.php`)**
   - **Issue:** Sleeper seats (berths) have a 1:2 height aspect ratio, which caused them to overflow their single grid slots and collide with the seats immediately below them in the grid.
   - **Fix:** Refactored grid rendering to position seats using explicit CSS grid row and column coordinates. Sleeper berths now get `grid-row: [R] / span 2; grid-column: [C]` so the grid engine reserves exactly 2 rows of space, resolving the overlap.

3. **Conflict Filtering for Overlapped Seats (`book.php`)**
   - **Issue:** If the database layout contains seat nodes directly beneath a sleeper berth, they would overlap.
   - **Fix:** Added a PHP loop to dynamically scan the coordinates, find any seat positioned directly below a sleeper berth, and filter it out (`unset`) before generating the DOM elements.

4. **Timeline Progress Separator (`search.php`)**
   - **Issue:** The line connecting departure and arrival times was hardcoded as light white (`rgba(255,255,255,0.15)`), which was invisible in light mode.
   - **Fix:** Replaced with `var(--border-glass)` and `opacity: 0.8` to dynamically adapt to both light/dark theme backgrounds.

---

### ⚙️ Search Engine & Form Logics
1. **Smart Linked Search Dropdowns (`index.php`, `search.php`, and `/ajax/get_destinations.php`)**
   - **Feature:** Replaced independent dropdown lists with linked AJAX filters. Selecting an origin ("Leaving From") calls the `/ajax/get_destinations.php` API to return only destinations that have an **active route** from that origin.
   - **Created File:** `ajax/get_destinations.php` is the JSON endpoint.

2. **Inline Modify Search (`search.php`)**
   - **Feature:** "Modify Search" previously redirected back to the home page. It now expands an inline collapsible search panel directly on `search.php`. Users can change the source, destination, or date, and search again without losing their place. Handles 0-result states gracefully.

3. **Trip Duration Calculation Bug (`search.php`)**
   - **Issue:** The duration calculation used `$duration->format('%h hrs %i mins')` which failed for multi-day voyages (e.g. departing May 27th and arriving May 28th at the same time) and printed `0 hrs 0 mins`.
   - **Fix:** Corrected to accumulate days into hours: `($duration->days * 24) + $duration->h`.

---

### 💳 Booking Flow & Checkout Logics
1. **Disabled Auto-Fill Details (`checkout.php`)**
   - **Fix:** Removed customer profile auto-fill logic for contact details. The fields for name and email now load completely blank.

2. **Adjacent Female Protection Restriction (`checkout.php`)**
   - **Feature:** Previously, if a male booked a seat next to a female, it threw an error alert popup only after form submission.
   - **Fix:** The checkout page now checks seat coordinates against previously booked female passengers. If a seat is adjacent, the "Male" option is removed from the passenger gender dropdown, defaulting to "Female", and an inline warning is shown: `⚠️ Adjacent to Female (Male not allowed)`.

---

### 🔒 Security & Hardening (Pre-Deployment)
1. **Setup Script Protection (`database/setup.php`)**
   - **Issue:** Anyone could trigger a full database reset by visiting `/database/setup.php`.
   - **Fix:** Integrated a lock file mechanism. After initial setup, it creates `/database/setup.lock`. Subsequent runs will fail unless the lock file is manually deleted.

2. **GET-based Transaction Protection (`cancellations.php` & `bookings.php`)**
   - **Issue:** Ticket cancellations were triggered via a simple GET request link, exposing it to CSRF.
   - **Fix:** Changed the link into a POST form with a CSRF token. `cancellations.php` now strictly validates the CSRF token and rejects non-POST requests.

3. **CLI Restriction (`database/run_migration.php`)**
   - **Fix:** Restrained execution of the migration runner strictly to CLI shell environments using `php_sapi_name() !== 'cli'`.

4. **Information Disclosure Prevention (`config/config.php`)**
   - **Fix:** Disabled display of raw PHP error stack traces on client viewports (`ini_set('display_errors', 0)`). Errors are kept logged to the local WAMP log.

5. **HTML tag formatting cleanup (`login.php`)**
   - **Fix:** Removed trailing unmatched closing `</div>` tags at the end of the script to prevent layout anomalies on mobile browsers.

6. **"Headers Already Sent" Redirect Fix (`agent/configure_layout.php`)**
   - **Issue:** Modifying layout templates threw an error when attempting to redirect using PHP `header()`, since the header include had already flushed HTML.
   - **Fix:** Replaced with a JavaScript redirect via `window.location.replace` when the template process completes.

7. **Agent Portal Default Root Routing (`agent/index.php`)**
   - **Fix:** Renamed `agent.php` to `index.php` inside the `/agent` directory so that typing `/agent/` in the URL automatically routes to the agent dashboard index file without throwing a 404 or folder directory index list.

---

## 3. Directory Layout & Key File Locations
- **CSS Style Sheets:** [assets/css/style.css](file:///c:/wamp64/www/Bus/assets/css/style.css) | [assets/css/theme.css](file:///c:/wamp64/www/Bus/assets/css/theme.css)
- **Database Schema:** [database/schema.sql](file:///c:/wamp64/www/Bus/database/schema.sql) | [database/migration_templates.sql](file:///c:/wamp64/www/Bus/database/migration_templates.sql)
- **Setup Lock File:** [database/setup.lock](file:///c:/wamp64/www/Bus/database/setup.lock)
- **AJAX Dynamic Routes:** [ajax/get_destinations.php](file:///c:/wamp64/www/Bus/ajax/get_destinations.php)
- **Pre-Deployment Checklist Summary:** [pre_deployment_audit_report.md](file:///C:/Users/Ganda/.gemini/antigravity/brain/6ccc3e2e-d039-4f7b-965c-671468d2acad/pre_deployment_audit_report.md)

All scripts compile cleanly and are fully tested for syntax compatibility under PHP 8.2+.
