/**
 * NotifyBay Frontend JavaScript.
 *
 * Responsibilities (PHP-first architecture):
 * - Variable product variation switching (waitlist form swap)
 * - Subscription AJAX (waitlist form submit)
 * - Cache-busting ping for single product pages (subscription state updates)
 * - Native WooCommerce notice integration
 *
 * Non-variable products and archives are fully rendered by PHP; JS only handles
 * interactivity and dynamic updates.
 *
 * Neutral extension points (used by a premium add-on's own script, never by Free):
 * - `notifybay:hydrated`     document event, fired after the batch guest-status
 *                            fetch, carrying { productId, variationSubscriptions }.
 * - `notifybay:unsubscribed` document event, fired after a My-Account removal,
 *                            carrying the server response as `detail`.
 * The delegated click handler is scoped to waitlist controls only, so an add-on
 * can bind its own handler for its controls without double-firing.
 */
(function ($) {
    'use strict'; // Enable strict mode to catch common coding mistakes

    /**
     * Main NotifyBay object containing all frontend logic.
     */
    const NotifyBay = {
        /**
         * Initialize the plugin logic when the script loads.
         */
        init: function () {
            // Bind all DOM event listeners (clicks, variation changes, etc.)
            this.bindEvents();

            // Store a reference to this object for use in callbacks
            const self = this;

            // Collect the unique product IDs present on the current page
            const productIds = [];
            $('.notifybay-frontend-root').each(function () {
                productIds.push($(this).data('product-id'));
            });

            // Attempt to retrieve a remembered guest email from a browser cookie
            const rememberedEmail = this.getCookie('notifybay_guest_email');

            // Batch hydrate guest subscriptions if the user is not logged in but has a remembered email
            if (!notifybay_vars.user.is_logged_in && rememberedEmail && productIds.length > 0) {
                // Remove any duplicate IDs from the array before sending to the server
                const uniqueIds = [...new Set(productIds)];

                // Trigger the batch AJAX request to fetch subscription statuses for all products at once
                self.batchHydrateGuestSubscriptions(uniqueIds, rememberedEmail);
            }
        },

        /**
         * Fetch and apply subscription states for guest users in bulk (cache-busting).
         *
         * @param {Array} productIds Array of product IDs to fetch
         * @param {string} email     The guest email from cookie
         */
        batchHydrateGuestSubscriptions: function (productIds, email) {
            // Store reference to self for AJAX callback
            const self = this;

            // Trigger GET request to the batch product status REST endpoint
            $.ajax({
                url: notifybay_vars.rest_url + '/batch-product-status',
                method: 'GET',
                // Send the REST nonce so the endpoint's permission_callback
                // (same-origin protection) accepts the request.
                headers: notifybay_vars.nonce ? { 'X-WP-Nonce': notifybay_vars.nonce } : {},
                data: {
                    email: email, // The email to check subscriptions for
                    product_ids: productIds // List of products to check
                },
                success: function (response) {
                    // Ensure the response contains the results object
                    if (!response.results) {
                        return;
                    }

                    // Iterate through each product ID returned in the result set
                    Object.keys(response.results).forEach(function (productId) {
                        // Extract the subscription data for this specific product
                        const data = response.results[productId];
                        if (!data.variation_subscriptions) {
                            return;
                        }

                        // Broadcast the hydrated map once per product so a premium
                        // add-on's script can update its own UI from the same payload.
                        document.dispatchEvent(new CustomEvent('notifybay:hydrated', {
                            detail: {
                                productId: productId,
                                variationSubscriptions: data.variation_subscriptions
                            }
                        }));

                        // Find all root containers matching this product ID on the page
                        const $roots = $('.notifybay-frontend-root[data-product-id="' + productId + '"]');

                        // Update each container found
                        $roots.each(function () {
                            const $root = $(this);

                            // Store the subscription map in the element's data for later retrieval
                            $root.data('variation-subscriptions', data.variation_subscriptions);

                            // Get the product type for this specific root
                            const productType = $root.data('product-type');

                            // If it's a simple product, apply states immediately
                            if (productType !== 'variable') {
                                // Simple products use index '0' for their status
                                const subs = data.variation_subscriptions[0] || {};
                                self.applySubscriptionStates($root, subs.waitlist, 'simple');
                            } else {
                                // For variable products, check if a variation is currently active
                                const $vForm = $root.closest('.product').find('.variations_form');
                                const currentVarId = $vForm.find('input.variation_id').val();

                                // If a variation is selected, refresh its UI state now
                                if (currentVarId > 0) {
                                    const variationData = $vForm.data('variation_data') || [];
                                    const variation = variationData.find(v => v.variation_id == currentVarId);
                                    if (variation) {
                                        self.handleVariationChange($root, variation);
                                    }
                                }
                            }
                        });
                    });
                }
            });
        },

        /**
         * Update the UI state of the waitlist buttons based on subscription status.
         *
         * @param {jQuery}  $root       The product container
         * @param {boolean} waitlist    Whether the user is on the waitlist
         * @param {string}  productType 'simple', 'variable', etc.
         */
        applySubscriptionStates: function ($root, waitlist, productType) {
            // Retrieve global settings from the localized variable
            const settings = notifybay_vars.settings;

            // Waitlist state is only meaningful for a concrete (simple/selected) product.
            if (productType === 'variable') {
                return;
            }

            if (waitlist) {
                // Mark waitlist buttons as active and disabled if subscribed
                $root.find('.notifybay-waitlist-btn, .notifybay-waitlist-trigger')
                    .addClass('active')
                    .prop('disabled', true)
                    .text('Already on Waitlist');
            } else {
                // Reset waitlist buttons to default state if not subscribed
                $root.find('.notifybay-waitlist-btn, .notifybay-waitlist-trigger')
                    .removeClass('active')
                    .prop('disabled', false)
                    .text(settings.waitlist_btn || 'Notify Me');
            }
        },

        /**
         * Bind global DOM events.
         */
        bindEvents: function () {
            // Reference self for event callbacks
            const self = this;

            // Listen for WooCommerce 'found_variation' event (variation selected)
            $(document).on('found_variation', '.variations_form', function (event, variation) {
                // Find the nearest NotifyBay root container
                const $root = $(this).closest('.product').find('.notifybay-frontend-root').first();
                if (!$root.length) return; // Exit if no root found
                // Handle the UI logic for the selected variation
                self.handleVariationChange($root, variation);
            });

            // Listen for WooCommerce 'reset_data' event (variation selection cleared)
            $(document).on('reset_data', '.variations_form', function () {
                // Find the nearest NotifyBay root container
                const $root = $(this).closest('.product').find('.notifybay-frontend-root').first();
                if (!$root.length) return; // Exit if no root found
                // Reset the UI to the default parent product state
                self.handleVariationReset($root);
            });

            // Use event delegation for NotifyBay submit/trigger buttons.
            document.addEventListener('click', function (e) {
                // Check if the click target or its parent is a NotifyBay submit button
                const target = e.target.closest('.notifybay-submit');
                if (!target) return; // Exit if not a NotifyBay button

                const $btn = $(target);

                // Free handles waitlist controls only. Every waitlist control lives
                // inside a wrapper marked `data-notifybay-type="waitlist"`; any other
                // submit control (e.g. one a premium add-on renders) is left for that
                // add-on's own handler, so the two document-level listeners never
                // double-fire.
                const $typeWrapper = $btn.closest('[data-notifybay-type]');
                if (!$typeWrapper.length || $typeWrapper.data('notifybay-type') !== 'waitlist') {
                    return;
                }

                // Stop default browser behavior for a recognized waitlist control
                e.preventDefault();
                e.stopPropagation();

                // Get remembered guest email if available
                const rememberedEmail = self.getCookie('notifybay_guest_email');

                // Handle 'Trigger' buttons (guest initial interaction to show form)
                if ($btn.hasClass('notifybay-waitlist-trigger')) {
                    const $wrapper = $btn.closest('.notifybay-waitlist-wrapper');
                    const $root = $btn.closest('.notifybay-frontend-root');

                    // UX Optimization: If guest is recognized, bypass form reveal and submit immediately
                    if (!notifybay_vars.user.is_logged_in && rememberedEmail) {
                        const $form = $btn.closest('form');
                        if ($root.length && $form.length) {
                            self.submitSubscription($root, $form, 'waitlist');
                        }
                        return;
                    }

                    // Otherwise, reveal the email input form to the guest
                    $wrapper.find('.notifybay-guest-trigger-wrapper').hide();
                    $wrapper.find('.notifybay-guest-form-wrapper').fadeIn();
                    $root.addClass('form-open'); // Mark root as having an open form
                    return;
                }

                // Handle actual 'Submit' buttons
                const $form = $btn.closest('form');
                const $root = $btn.closest('.notifybay-frontend-root');

                // If both root and form are present, proceed to submission
                if ($root.length && $form.length) {
                    self.submitSubscription($root, $form, 'waitlist');
                }
            });

            // Handle subscription removal from the 'My Account' table
            $(document).on('click', '.notifybay-remove-subscription', function (e) {
                e.preventDefault(); // Prevent link default behavior
                const $btn = $(this);
                const leadId = $btn.data('lead-id'); // Get the unique lead ID
                // Trigger the removal logic
                self.removeSubscription($btn, leadId);
            });
        },

        // =====================================================================
        // Variable Product Variation Handling
        // =====================================================================

        /**
         * Handles logic when a variation is changed.
         *
         * @param {jQuery} $root     The product container
         * @param {object} variation The variation data from WooCommerce
         */
        handleVariationChange: function ($root, variation) {
            // Find the main WooCommerce 'Add to Cart' button
            const $cartBtn = $root.closest('.product').find('.single_add_to_cart_button');

            // Extract stock and backorder settings for this variation
            const backorderMode = notifybay_vars.settings.backorder_mode;
            const isOutOfStock = !variation.is_in_stock;
            const backordersAllowed = variation.backorders_allowed;

            // Logic: Decide whether to display the waitlist form
            let showWaitlist = false;
            if (isOutOfStock) {
                // Forced waitlist mode or standard check if backorders are blocked
                showWaitlist = (backorderMode === '1') ? true : !backordersAllowed;
            }

            // Clean up any existing waitlist form for the previous variation
            $root.find('.notifybay-waitlist-wrapper').remove();

            if (showWaitlist) {
                // --- Out of Stock: Show Waitlist ---
                // Hide the default cart button and render the form for this variation.
                $cartBtn.hide();
                this.renderWaitlistForm($root, variation.variation_id);

                // Check pre-hydrated cache to see if the user is already on the waitlist for this variant
                const variationSubscriptions = $root.data('variation-subscriptions') || {};
                const subStatus = variationSubscriptions[variation.variation_id] || {};
                this.applySubscriptionStates($root, subStatus.waitlist, 'simple');
            } else {
                // --- In Stock / Unavailable: no waitlist ---
                // Let WooCommerce manage the cart button for this variation.
                if (variation.is_purchasable) {
                    $cartBtn.show();
                } else {
                    $cartBtn.hide();
                }
            }
        },

        /**
         * Reset UI when variation selection is cleared.
         *
         * @param {jQuery} $root The product container
         */
        handleVariationReset: function ($root) {
            // Find the WooCommerce cart button and ensure it's visible
            const $cartBtn = $root.closest('.product').find('.single_add_to_cart_button');
            $cartBtn.show();
            // Clear out variation-specific waitlist forms
            $root.find('.notifybay-waitlist-wrapper').remove();
        },

        // =====================================================================
        // Rendering Helpers
        // =====================================================================

        /**
         * Render the Waitlist signup form dynamically (Variable products).
         *
         * @param {jQuery} $root       The product container
         * @param {int}    variationId The ID of the selected variation
         */
        renderWaitlistForm: function ($root, variationId) {
            // Extract global variables for template generation
            const is_logged_in = notifybay_vars.user.is_logged_in;
            const user_email = notifybay_vars.user.email;
            const settings = notifybay_vars.settings;

            let formContent = ''; // Variable to hold the generated HTML content

            // Template for logged in users (1-click)
            if (is_logged_in) {
                formContent = `
                    <input type="hidden" name="notifybay_email" value="${user_email}">
                    <button type="button" class="notifybay-waitlist-btn notifybay-submit button alt ${settings.waitlist_class}">
                        ${settings.waitlist_btn}
                    </button>
                `;
            }
            // Template for guests (Email form)
            else {
                let expiryField = ''; // Expiry dropdown if enabled
                if (settings.expiry_enabled) {
                    expiryField = `
                        <select name="notifybay_expiry">
                            <option value="">No expiry</option>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30">30 days</option>
                        </select>
                    `;
                }

                // Guest UI with initial trigger button and hidden form wrapper
                formContent = `
                    <div class="notifybay-guest-trigger-wrapper">
                        <button type="button" class="notifybay-waitlist-trigger notifybay-submit button alt ${settings.waitlist_class}">
                            ${settings.waitlist_btn}
                        </button>
                    </div>
                    <div class="notifybay-guest-form-wrapper" style="display:none;">
                        <p class="notifybay-waitlist-desc">This item is currently out of stock. Join our waitlist to be notified when it returns!</p>
                        <div class="notifybay-form-fields">
                             <input type="email" name="notifybay_email" placeholder="Your email address">
                            ${expiryField}
                             <button type="button" class="notifybay-guest-submit notifybay-submit button alt ${settings.waitlist_class}">${settings.waitlist_btn}</button>
                        </div>
                    </div>
                `;
            }

            // Full form wrapper with hidden variation_id field
            const formHtml = `
                <div class="notifybay-waitlist-wrapper" data-notifybay-type="waitlist">
                    <form class="notifybay-waitlist-form" onsubmit="return false;">
                        ${formContent}
                        <input type="hidden" name="variation_id" value="${variationId}">
                    </form>
                </div>
            `;
            // Append the generated form to the product container
            $root.append(formHtml);
        },

        // =====================================================================
        // Notifications
        // =====================================================================

        /**
         * Show a native-looking WooCommerce notice on the page.
         *
         * @param {string} message The message to display
         * @param {string} type    Notice type: 'success' or 'error'
         */
        showNotice: function (message, type = 'success') {
            // Find the standard WooCommerce notice wrapper
            const $wrapper = $('.woocommerce-notices-wrapper').first();
            if (!$wrapper.length) return; // Exit if not found

            // Determine correct CSS classes based on message type
            const noticeClass = (type === 'success') ? 'woocommerce-message' : 'woocommerce-error';

            // Generate appropriate HTML structure for success vs error
            const noticeHtml = (type === 'success')
                ? `<div class="${noticeClass}" role="alert">${message}</div>`
                : `<ul class="${noticeClass}" role="alert"><li>${message}</li></ul>`;

            // Inject the notice into the wrapper
            $wrapper.html(noticeHtml);

            // Smoothly scroll the browser to the notice location
            $('html, body').animate({
                scrollTop: $wrapper.offset().top - 100
            }, 500);
        },

        // =====================================================================
        // Cookies
        // =====================================================================

        /**
         * Retrieve the value of a specific cookie.
         *
         * @param {string} name Cookie name
         * @returns {string|null} Cookie value or null if not found
         */
        getCookie: function (name) {
            // Append semicolon to document.cookie for easier splitting
            const value = `; ${document.cookie}`;
            // Split by the cookie name search pattern
            const parts = value.split(`; ${name}=`);
            // If split produced 2 parts, we found the cookie
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null; // Otherwise return null
        },

        /**
         * Set a browser cookie.
         *
         * @param {string} name  Cookie name
         * @param {string} value Cookie value
         * @param {number} days  Days until expiration
         */
        setCookie: function (name, value, days = 30) {
            let expires = "";
            // Calculate expiration date string if days provided
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            // Write to document.cookie with security attributes
            document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
        },

        // =====================================================================
        // Subscription AJAX
        // =====================================================================

        /**
         * Submit a waitlist subscription request via AJAX.
         *
         * @param {jQuery} $root    Product root container
         * @param {jQuery} $element The form or clicked element
         * @param {string} type     Lead type (Free only submits 'waitlist')
         */
        submitSubscription: function ($root, $element, type) {
            // Store self reference
            const self = this;
            // Get product ID from root
            const productId = $root.data('product-id');

            if (!$root.length) return; // Exit if invalid root

            let variationId = 0; // Variation ID (0 if simple)
            let expiry = 0; // Expiry days

            // Get remembered guest email from cookie
            const rememberedEmail = this.getCookie('notifybay_guest_email');

            // Try to get email from input field
            let email = $element.find('input[name="notifybay_email"]').val();

            // Fallback to cookie if guest input is empty
            if (!email && !notifybay_vars.user.is_logged_in && rememberedEmail) {
                email = rememberedEmail;
            }

            // Extract variation ID from form or WooCommerce global hidden input
            variationId = $element.find('input[name="variation_id"]').val() || $root.closest('.product').find('.variation_id').val() || 0;
            // Extract expiry selection
            expiry = $element.find('select[name="notifybay_expiry"]').val() || 0;

            // Validate that an email is present
            if (!email) {
                self.showNotice("Please enter a valid email address.", 'error');
                return;
            }

            // UI State: Set button to 'Processing' and disable it
            const $btn = $element.find('.notifybay-submit').first();
            const originalText = $btn.text();
            $btn.text('Processing...').prop('disabled', true);

            // AJAX POST request to the subscription endpoint
            $.ajax({
                url: notifybay_vars.rest_url + '/subscribe',
                method: 'POST',
                beforeSend: function (xhr) {
                    // Set WordPress security nonce header
                    if (notifybay_vars.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', notifybay_vars.nonce);
                    }
                },
                data: {
                    email: email,
                    product_id: productId,
                    variation_id: variationId,
                    type: type,
                    expiry: expiry
                },
                success: function (response) {
                    // Show success notice
                    self.showNotice(response.message, 'success');

                    // Update guest cookie to remember this email
                    if (!notifybay_vars.user.is_logged_in) {
                        self.setCookie('notifybay_guest_email', email);
                    }

                    // Mark waitlist as subscribed
                    $btn.text('Already on Waitlist').prop('disabled', true).addClass('active');
                    // Hide input fields and description
                    $element.find('.notifybay-waitlist-desc, .notifybay-form-fields').hide();

                    // Sync state of trigger button (especially for guests)
                    const $trigger = $element.find('.notifybay-waitlist-trigger');
                    if ($trigger.length) {
                        $trigger.text('Already on Waitlist').prop('disabled', true).addClass('active');
                        $element.find('.notifybay-guest-trigger-wrapper').show();
                    }

                    // Clean up root classes
                    $root.removeClass('form-open');

                    // Synchronize the local subscription cache to prevent state loss on variation switch
                    const subsCache = $root.data('variation-subscriptions') || {};
                    const vid = variationId || 0;
                    if (!subsCache[vid]) subsCache[vid] = {};
                    subsCache[vid][type] = true;
                    $root.data('variation-subscriptions', subsCache);
                },
                error: function (xhr) {
                    // Show error notice
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Error occurred";
                    self.showNotice(msg, 'error');
                    // Reset button to original state to allow retry
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Remove a lead/subscription via AJAX.
         *
         * @param {jQuery} $btn   The delete button clicked
         * @param {int}    leadId The ID of the lead to remove
         */
        removeSubscription: function ($btn, leadId) {
            // Reference self
            const self = this;
            // Find the table row containing the button
            const $row = $btn.closest('.notifybay-lead-row');

            // UI State: Loading
            $btn.prop('disabled', true).text('Removing...');

            // AJAX request to the AJAX-optimized unsubscribe endpoint
            $.ajax({
                url: notifybay_vars.rest_url + '/unsubscribe-ajax',
                method: 'POST',
                beforeSend: function (xhr) {
                    // Security nonce
                    if (notifybay_vars.nonce) {
                        xhr.setRequestHeader('X-WP-Nonce', notifybay_vars.nonce);
                    }
                },
                data: {
                    lead_id: leadId // Lead to delete
                },
                success: function (response) {
                    // Show success notice
                    self.showNotice(response.message, 'success');

                    // Broadcast so a premium add-on's script can refresh its own UI
                    // (e.g. a nav badge) from the response.
                    document.dispatchEvent(new CustomEvent('notifybay:unsubscribed', { detail: response }));

                    // Smoothly remove the row from the DOM
                    $row.fadeOut(function () {
                        $(this).remove(); // Remove element after animation
                        // If no rows left in table, reload to show empty state message
                        const $table = $('.notifybay-account-table');
                        if ($table.find('tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                },
                error: function (xhr) {
                    // Error notice
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "Error occurred";
                    self.showNotice(msg, 'error');
                    // Restore button state
                    $btn.prop('disabled', false).text('Remove');
                }
            });
        }
    };

    /**
     * Entry Point: Initialize NotifyBay when the document is ready.
     */
    $(document).ready(function () {
        NotifyBay.init();
    });

})(jQuery); // End of IIFE wrapper

/**
 * ============================================================================
 * NotifyBay Frontend Flowchart & Decision Logic
 * ============================================================================
 *
 * 1. INITIALIZATION FLOW
 *    [Page Load] --> Scan for .notifybay-frontend-root elements
 *     |--> Extract unique product IDs.
 *     |--> Are there guest cookies stored (notifybay_guest_email)?
 *          |--> Yes: Send single /batch-product-status AJAX request.
 *          |--> No: Do nothing (wait for user interaction).
 *
 * 2. BATCH HYDRATION RESULT (GUESTS)
 *    [Batch API Returns] --> Update pre-hydration cache (`data('variation-subscriptions')`)
 *                            and dispatch `notifybay:hydrated` per product.
 *     |--> Is it a simple product?
 *          |--> Yes: Call applySubscriptionStates() immediately.
 *          |--> No: (Variable product) Wait for the user to select a variation.
 *
 * 3. VARIATION CHANGE FLOW
 *    [User Selects Variation] --> Trigger 'found_variation' event
 *     |--> Check Variation Stock Status & Backorder Settings:
 *          |--> Out of Stock (and Waitlist active)
 *               - Hide "Add to Cart" button.
 *               - Render dynamic Waitlist Form.
 *               - Pull cached subscription state and update buttons (e.g., "Already on Waitlist").
 *          |--> In Stock / Unavailable
 *               - Remove Waitlist Form; let WooCommerce manage the cart button.
 *
 * 4. BUTTON CLICK (SUBSCRIPTION) FLOW
 *    [User Clicks "Notify Me"]
 *     |--> Is it a "Trigger" button (guest initial interaction)?
 *          |--> Does the guest have a stored email cookie?
 *               |--> Yes: BYPASS form reveal, auto-submit AJAX instantly.
 *               |--> No: Reveal email input form (slide down).
 *     |--> Is it a "Submit" button?
 *          |--> Extract email (from input or cookie fallback).
 *          |--> Send POST /subscribe AJAX.
 *          |--> On Success: Update UI text to "Already...", hide form inputs, set guest cookie, update local cache.
 * ============================================================================
 */
