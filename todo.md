PROJECT UPDATE – AUTHENTICATION, UI/UX, THEME REFACTOR, BUS LAYOUT IMPROVEMENTS & SYSTEM FIXES

IMPORTANT:

- Do NOT break any existing functionality.
- Preserve current PHP architecture wherever possible.
- Maintain backward compatibility.
- Fix existing bugs before implementing new features.
- Focus heavily on UI consistency, authentication separation, seat management usability, and light theme quality.
- Ensure all changes are production-ready.

==================================================
PRIORITY ORDER
==================================================

Before implementing new features:

1. Fix Authentication Separation
2. Fix Entire Theme System
3. Fix Discount System
4. Improve Bus Layout Builder
5. Add Remaining Features

Do not build new features on top of broken functionality.

==================================================
1. SEPARATE CUSTOMER / AGENT / ADMIN AUTHENTICATION
==================================================

Current issue:

Customer website shows "Staff Register".

This is incorrect.

--------------------------------------------------

CUSTOMER WEBSITE

Only allow:

- Customer Login
- Customer Registration

Remove:

- Staff Register
- Agent Register
- Admin Register

Customer accounts should only access customer features.

--------------------------------------------------

AGENT LOGIN

Create:

/agent/agent.php

Allow:

- Agent Login
- Super Admin Login

Role-based access after login.

--------------------------------------------------

ADMIN LOGIN

Create:

/admin/login.php

Allow:

- Admin Login
- Super Admin Login

Admin-only access.

--------------------------------------------------

ROLE SEPARATION

Customer:
- Browse
- Book Tickets
- View Booking History

Agent:
- Manage Buses
- Manage Routes
- Manage Trips
- Seat Operations

Admin:
- Full System Access

Prevent cross-role authentication confusion.

==================================================
2. SEAT NUMBERING SYSTEM IMPROVEMENT
==================================================

Current seat naming:

S1
S2
S3

Too generic.

Implement realistic numbering.

--------------------------------------------------

SEATER EXAMPLES

A1 A2
B1 B2
C1 C2

--------------------------------------------------

SLEEPER EXAMPLES

L1
L2
L3

--------------------------------------------------

DOUBLE SLEEPER EXAMPLES

Upper:

U1
U2
U3

Lower:

D1
D2
D3

--------------------------------------------------

CUSTOM LAYOUTS

Automatically generate seat names using:

Row + Position

Example:

A1 A2 A3 A4
B1 B2 B3 B4
C1 C2 C3 C4

==================================================
3. BUS LAYOUT BUILDER REDESIGN
==================================================

Current builder is difficult to use.

Create a more user-friendly visual builder.

Features:

- Drag and Drop Seats
- Real-Time Bus Preview
- Add Row
- Remove Row
- Add Seat
- Remove Seat
- Seat Type Selector
- Seat Position Editor
- Bulk Seat Creation

Provide live visual feedback.

==================================================
4. SAVEABLE LAYOUT TEMPLATES
==================================================

Allow agents to save layouts as templates.

Examples:

- 2x2 Seater
- 2x1 Sleeper
- Volvo Sleeper
- Luxury Double Sleeper

Features:

Save Template
Edit Template
Delete Template
Reuse Template

When creating a new bus:

Agent can:

- Create New Layout
OR
- Apply Existing Template

This prevents rebuilding layouts repeatedly.

==================================================
5. SCHEDULE TRIPS PAGE FIXES
==================================================

Current issue:

Light theme is rendering incorrectly.

Fix:

- Cards
- Tables
- Forms
- Inputs
- Dropdowns
- Text Contrast
- Alerts
- Modals

Ensure proper rendering.

==================================================
6. DISCOUNT SYSTEM FIX
==================================================

Current discount logic is incorrect.

Fix calculations.

--------------------------------------------------

Percentage Example

Base Fare = ₹1000

10% Discount

Final Fare = ₹900

--------------------------------------------------

Fixed Discount Example

Base Fare = ₹1000

₹100 Discount

Final Fare = ₹900

--------------------------------------------------

Validation

Discount must never exceed ticket price.

Apply correctly in:

- Seat Selection
- Checkout
- Payment
- Booking Summary
- Ticket PDF
- Booking History

==================================================
7. COMPLETE LIGHT THEME AUDIT
==================================================

Current issue:

Many pages still contain:

- Dark colors
- Poor contrast
- Inconsistent styling

Perform a full audit.

Review every page:

Customer Panel
Agent Panel
Admin Panel
Login Pages
Register Pages
Booking Pages
Seat Selection
Manage Buses
Manage Routes
Schedule Trips
Hold/Release Seats
Bookings
Settlements
Reports
Notifications
Cancellation Pages

Fix all inconsistencies.

==================================================
8. COMPLETE LIGHT THEME REDESIGN
==================================================

If necessary, recolor the entire platform.

Goal:

Premium Luxury Travel Platform

Light Theme Palette:

Background:
#F8F5EF

Secondary Background:
#FCFAF7

Cards:
#FFFFFF

Primary Accent:
#C8A96B

Secondary Accent:
#D4AF37

Primary Text:
#1F1F1F

Secondary Text:
#5C5C5C

Border:
#E7E1D7

Success:
#2E7D32

Warning:
#ED6C02

Danger:
#D32F2F

Remove purple/neon styling.

==================================================
9. DARK MODE IMPROVEMENT
==================================================

Maintain dark mode support.

Palette:

Background:
#0F1115

Secondary:
#171A21

Cards:
#1D212B

Accent:
#D4AF37

Primary Text:
#F5F5F5

Secondary Text:
#B0B0B0

Borders:
#2D3442

Ensure consistency.

==================================================
10. CENTRALIZED THEME SYSTEM
==================================================

Create a single source of truth.

Example:

assets/css/theme.css

Store ALL colors as variables.

Example:

:root {

--bg-primary
--bg-secondary
--card-bg

--text-primary
--text-secondary

--accent-primary
--accent-secondary

--success
--warning
--danger

--border-color

}

Dark mode should override variables only.

No hardcoded colors.

==================================================
11. REMOVE HARDCODED COLORS
==================================================

Replace hardcoded colors throughout project.

Examples:

#000000
#111111
#222222
#333333
#ffffff

Replace with theme variables.

Every page must use centralized theme variables.

==================================================
12. COMPONENT STANDARDIZATION
==================================================

Create reusable styling for:

Buttons
Cards
Forms
Inputs
Tables
Dropdowns
Alerts
Modals
Sidebars
Navbar
Seat Cards
Dashboard Widgets

No page should have its own color system.

==================================================
13. THEME PREFERENCE STORAGE
==================================================

Store selected theme in:

- Local Storage
or
- Database User Preferences

Theme should persist after logout/login.

==================================================
14. RESPONSIVENESS REVIEW
==================================================

Review:

Desktop
Tablet
Mobile

Fix:

- Overflow
- Broken layouts
- Sidebar issues
- Table responsiveness
- Seat map responsiveness
- Form responsiveness

==================================================
15. CODE CLEANUP
==================================================

Reduce duplication.

Create reusable:

- Button Classes
- Card Classes
- Form Classes
- Table Classes
- Modal Classes

Use a unified design system across:

Customer Portal
Agent Portal
Admin Portal

==================================================
16. PERFORMANCE OPTIMIZATION
==================================================

Remove:

- Duplicate CSS
- Unused CSS

Optimize:

- Theme switching
- DOM updates
- Rendering performance

Theme switching should be instant.

==================================================
17. QUALITY ASSURANCE
==================================================

Before completion:

Verify:

- Customer Login
- Customer Registration
- Agent Login
- Admin Login

Verify:

- Theme consistency
- Discount calculations
- Layout templates
- Seat numbering
- Mobile responsiveness

Review every page in:

- Light Mode
- Dark Mode

==================================================
FINAL GOAL
==================================================

Transform the platform into a professional, premium, production-ready bus booking system.

Inspiration:

- RedBus
- Airline Reservation Systems
- Luxury Travel Portals

Focus on:

- Clean UI
- Consistent Theme
- Better UX
- Maintainable Code
- Fast Performance
- Proper Authentication Separation

Do not leave any page partially themed or partially updated.