# NotifyBay — Implementation Plan

## 1. Overview
This project adds a global setting to enable or disable the Wishlist feature across the entire NotifyBay plugin. It allows store administrators to turn off Wishlist functionality (price drop notifications) while keeping Waitlist functionality (back-in-stock notifications) active.

## 2. Assumptions
- Stack auto-detected from filesystem: PHP 7+, WordPress/WooCommerce, React (TypeScript), Tailwind CSS.
- The new setting will be called `general_wishlistEnabled` and default to `true`.
- Disabling the wishlist globally should hide the "Add to Wishlist" buttons on product pages, hide the "Wishlist" tab in the WooCommerce "My Account" area, and block new wishlist subscriptions via the API.
- Existing wishlist subscriptions will remain in the database but will not be actionable while the feature is disabled.

## 3. Tech Stack

| Layer | Technology | Reason |
|-------|-----------|--------|
| Frontend | React, TS, Tailwind | Admin dashboard settings UI is built with React |
| Backend | PHP | WordPress plugin backend |
| Database | MySQL | Storing settings via WP Options API |

## 4. Architecture Overview
The toggle setting is stored in the WordPress database alongside other plugin settings. The React admin SPA will expose a UI toggle to update this value. The PHP backend (ProductPage, MyAccount, FrontendController) will query this setting and conditionally render HTML or process API requests.

```text
[ React Admin UI ] -- REST API --> [ WP Options (DB) ]
                                          |
                                          | (Setting read via Settings::get_settings)
                                          v
                              [ PHP Backend (ProductPage/API) ]
                                          |
                                          v
                               [ WooCommerce Frontend ]
```

## 5. Proposed File Structure
We are modifying existing files, not creating new ones.

```text
notifybay/
├── config/
│   └── settings.php           # Add default and schema for general_wishlistEnabled
├── src/
│   └── pages/
│       └── Settings.tsx       # Add toggle to the React admin interface
└── app/
    ├── Frontend/
    │   ├── ProductPage.php    # Check setting before rendering wishlist button
    │   └── MyAccount.php      # Check setting before rendering wishlist tab
    └── Api/
        └── FrontendController.php # Prevent wishlist subscribe API if disabled
```

## 6. UI Mockups

#### Settings Page — General Logic View
```text
+---------------------------------------------------------------------------------+
| NotifyBay Settings                                                              |
+---------------------------------------------------------------------------------+
| [ General ] | Appearance | Waitlist Engine | Emails | Advanced                  |
+=================================================================================+
| General Logic                                                                   |
|                                                                                 |
| Enable Plugin                                                                   |
| [x] Enable NotifyBay features                                                   |
|                                                                                 |
| Enable Wishlist                                                                 |
| [x] Enable wishlist functionality across the store                              |
|                                                                                 |
| Double Opt-In                                                                   |
| [ ] Requires guest users to verify their email address                          |
|                                                                                 |
|                                                                    [ Save ]     |
+---------------------------------------------------------------------------------+
```

## 7. Task List

### Task 1 — Add Setting to Configuration
**Category**: Config
**Effort**: S
**Depends on**: None

Add `general_wishlistEnabled` to the `defaults` array (set to `true`) and the `schema` array (type `boolean`) in `config/settings.php`. This registers the setting with the backend and makes it available via the REST API.

**Acceptance Criteria**:
- [ ] `general_wishlistEnabled` is present in `config/settings.php`.
- [ ] Setting defaults to `true`.

### Task 2 — Add Toggle to React Admin UI
**Category**: Frontend
**Effort**: S
**Depends on**: Task 1

Update `src/pages/Settings.tsx` to include a new `ClassicCheckbox` for the `general_wishlistEnabled` setting. Place it directly below the "Enable Plugin" (`general_isEnabled`) option in the "General Logic" section.

**Acceptance Criteria**:
- [ ] Toggle appears on the settings page.
- [ ] Toggling and saving updates the setting in the database.

### Task 3 — Enforce Setting on Frontend Rendering
**Category**: Backend
**Effort**: M
**Depends on**: Task 1

Modify `app/Frontend/ProductPage.php` and `app/Frontend/MyAccount.php` to check the `general_wishlistEnabled` setting. If false, do not render the "Add to Wishlist" button on product pages or archives, and do not register/render the "Wishlist" tab in the WooCommerce "My Account" area.

**Acceptance Criteria**:
- [ ] Wishlist buttons disappear from product pages when setting is disabled.
- [ ] Wishlist tab disappears from "My Account" when setting is disabled.

### Task 4 — Enforce Setting in REST API
**Category**: Backend
**Effort**: S
**Depends on**: Task 1

Modify `app/Api/FrontendController.php`. In the `subscribe` endpoint, return an error if a user tries to subscribe to the `wishlist` type while the feature is globally disabled. Also, exclude wishlist status checks in `get_batch_product_status` if disabled to save DB queries.

**Acceptance Criteria**:
- [ ] API rejects wishlist subscriptions when disabled.
- [ ] Batch status check API doesn't query wishlist tables if disabled.

## 8. Total Effort Summary

| Effort | Count | Approx. Time |
|--------|-------|--------------|
| S      |   3   | ~3 hrs       |
| M      |   1   | ~half day    |
| L      |   0   | 0 days       |
| XL     |   0   | 0 days       |
| **Total** | **4 tasks** | **~1 day (1 developer)** |

This is a very small feature addition that primarily involves checking a new boolean flag across existing backend entry points and adding one checkbox to the React UI.

## 9. Dependencies & Sequencing

**Critical path** (must be sequential):
Task 1 (Config) --> Task 2 (React UI) --> Task 3 & 4 (Backend Enforcements)

**Can be parallelised after Task 1:**
- Task 2 (React UI) is independent of Task 3 & 4.
- Task 3 (Frontend Rendering) and Task 4 (API) can be done in any order.

## 10. Risks & Mitigations

| # | Risk | Likelihood | Impact | Mitigation |
|---|------|-----------|--------|------------|
| 1 | Existing wishlist items in cart/session cause errors if setting toggled off mid-session. | Low | Low | The wishlist is primarily a database entity (Leads table); UI elements just disappear. Ensure shortcodes fail gracefully if rendered. |
| 2 | Background dispatcher still queries for wishlist items even if UI is disabled, wasting resources. | Medium | Low | Wait, should we disable the dispatcher too? Add a check in `app/Engine/Dispatcher.php` to skip wishlist queries if setting is disabled. |

## 11. Open Questions
1. Should the `app/Engine/Dispatcher.php` completely ignore existing active wishlist leads (meaning no price drop emails will be sent for previously saved items) when the wishlist feature is disabled? (Assuming YES for now).
2. Do we need to hide the "Wishlist" shortcode `[notifybay_wishlist]` output if the feature is disabled? (Assuming YES).

## 12. Out of Scope
- Deleting existing wishlist records from the database when the feature is turned off.
- Changing Waitlist functionality.