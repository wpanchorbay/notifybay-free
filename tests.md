# NotifyBay Email & Subscription Test Plan

This document outlines the test cases required to verify the core subscription and email functionality for both Waitlists and Wishlists.

## 1. Waitlist Subscriptions (Out of Stock)

### 1.1 Guest User
- [ ] **Email Prompt:** Verify that clicking "Notify Me" reveals an email input field for guests.
- [ ] **Successful Subscription:** Enter a valid email and submit. Verify the success message appears.
- [ ] **Email Verification (MailHog):** Visit http://localhost:8025/ and verify the "Confirm your subscription" email is received (if verification is enabled).
- [ ] **Duplicate Handling:** Try subscribing again with the same email. Verify the duplicate error message appears.
- [ ] **Nonce Check:** Verify the POST request succeeds without throwing a `rest_cookie_invalid_nonce` error.

### 1.2 Logged-in User
- [ ] **One-Click Signup:** Verify that clicking "Notify Me" instantly subscribes the user without asking for an email.
- [ ] **Successful Subscription:** Verify the success message appears and the button state changes.
- [ ] **Confirmation Email (MailHog):** Verify a subscription confirmation email is received in MailHog.
- [ ] **Restock Notification (MailHog):** Manually restock the product in WooCommerce and verify the notification email is sent to http://localhost:8025/.
- [ ] **Duplicate Handling:** Verify that the system prevents duplicate subscriptions.
- [ ] **Nonce Check:** Verify the POST request succeeds and includes a valid `X-WP-Nonce`.

---

## 2. Wishlist Subscriptions (In Stock)

### 2.1 Guest User
- [ ] **Email Prompt:** Verify that clicking "Save to Wishlist" reveals an email input field.
- [ ] **Successful Subscription:** Enter a valid email and submit. Verify the success message appears.
- [ ] **Email Verification (MailHog):** Visit http://localhost:8025/ and verify the "Confirm your wishlist subscription" email is received (if verification is enabled).
- [ ] **Duplicate Handling:** Try subscribing again with the same email. Verify the duplicate error message appears.

### 2.2 Logged-in User
- [ ] **One-Click Signup:** Verify that clicking "Save to Wishlist" instantly subscribes the user.
- [ ] **Successful Subscription:** Verify the success message appears and the button state changes to "Already in Wishlist".
- [ ] **Confirmation Email (MailHog):** Verify a wishlist confirmation email is received in MailHog.
- [ ] **Price Drop Alert (MailHog):** Reduce the product price in WooCommerce and verify the Price Drop notification is sent to http://localhost:8025/.
