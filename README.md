# NotifyBay

A sophisticated Waitlist and Wishlist marketing automation plugin for WooCommerce. Built with a modern React/TypeScript admin UI and a robust, high-performance PHP backend.

---

## Table of Contents
1. [Core Purpose](#core-purpose)
2. [Key Features](#key-features)
3. [Architecture Overview](#architecture-overview)
4. [Directory Structure](#directory-structure)
5. [Installation & Setup](#installation--setup)
6. [Development Workflow](#development-workflow)

---

## Core Purpose

NotifyBay bridges the gap between customer demand and inventory availability. It allows WooCommerce store owners to capture high-intent leads when products are out of stock (Waitlists) or when customers want to monitor price changes (Wishlists). 

By leveraging background processing and intelligent notification logic, NotifyBay recovers lost revenue without impacting site performance.

---

## Key Features

### Powerful Engine (PHP)
- **Waitlists & Back-in-Stock Alerts:** Automatically notifies customers when inventory is replenished.
- **Wishlists & Price Drop Alerts:** Allows customers to track products and alerts them when prices fall.
- **Fair-Play Engine:** Intelligent dispatch logic (`app/Engine/Dispatcher.php`) that calculates 'available for notification' stock to prevent overselling. It respects a configurable 'Reservation Window' to ensure fair distribution of notifications.
- **Asynchronous Processing:** High-volume email dispatching is handled in the background via Action Scheduler, ensuring zero impact on the customer checkout experience.
- **Conversion Tracking:** Deep integration with WooCommerce order hooks (`app/Core/WooCommerceHooks.php`) accurately attributes recovered revenue to NotifyBay alerts.
- **Guest to User Merging:** Seamlessly merges guest subscriptions into user accounts upon registration.

### Modern Admin Experience (React/TS)
- **Single Page Application (SPA):** A fast, responsive dashboard built with React and `react-router-dom`.
- **Advanced Analytics:** Visualizes 'Potential Revenue' vs. 'Recovered Revenue' to prove ROI.
- **Two Component Libraries:** Ships with both a modern Tailwind-styled UI and a 'Classic' component set that mimics native WordPress admin screens.
- **Settings API Integration:** Declarative settings schema managed via the React frontend.

---

## Architecture Overview

```
NotifyBay (Plugin Root)
├─ app/                     # PHP backend
│  ├─ Admin/               # Admin menu, script enqueuing
│  ├─ Api/                 # Versioned REST API controllers (Admin & Frontend)
│  ├─ Core/                # Base plugin lifecycle, Settings, WC Hooks
│  ├─ Data/                # DB schema & custom table management
│  ├─ Engine/              # Dispatcher (Fair-Play) and Worker (Action Scheduler)
│  ├─ Frontend/            # Product page injection (Buttons, Forms, FOMO banners)
│  ├─ Helper/              # Utilities, Logger
│  └─ Models/              # Lead management and data models
├─ config/                  # Registrations for API, core, migrations
├─ src/                     # React/TS SPA (Admin UI)
│  ├─ components/          # UI components (classics & common)
│  ├─ pages/               # Route views (Dashboard, Leads, Settings)
│  ├─ store/               # Context API state
│  ├─ styles/              # SCSS & Tailwind configuration
│  ├─ utils/               # API helpers, types, hooks
│  ├─ App.tsx              # Router & layout orchestration
│  └─ index.tsx            # Entry point
├─ assets/                  # Images, icons, static media
├─ build/                   # Compiled JS/CSS assets
├─ languages/               # i18n files (.pot)
├─ templates/               # Overridable email and frontend templates
├─ vendor/                  # Composer dependencies (including Action Scheduler)
└─ notifybay-waitlist-and-stock-alert-woo.php  # Plugin bootstrap
```

---

## Installation & Setup

1. **Install Dependencies**
   ```bash
   npm install            # Install Node packages for the React admin
   composer install       # Install PHP dependencies
   ```

2. **Build Assets**
   ```bash
   # Development build (watches for changes)
   npm run start
   
   # Production build
   npm run build
   ```

3. **Activate**
   Activate "NotifyBay" in the WordPress admin area. The custom database tables will be created automatically upon activation.

---

## Development Workflow

1. **Frontend Development:**
   Run `npm run start` to start the Webpack dev server. Changes in `src/` will hot-reload. Ensure `SCRIPT_DEBUG=true` is set in your `wp-config.php`.
2. **Backend Development:**
   PHP changes in `app/` take effect immediately upon page refresh.
3. **Tailwind:**
   NotifyBay uses a specific prefix (`notifybay-`) and a preflight guard to ensure Tailwind styles do not break native WordPress admin styling.
4. **Testing:**
   Test email dispatch logic by manually triggering Action Scheduler jobs or adjusting product stock levels in WooCommerce.

---

*This plugin is designed for high-performance WooCommerce environments requiring reliable, scalable customer notifications.*