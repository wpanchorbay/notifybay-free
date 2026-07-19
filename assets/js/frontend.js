/**
 * NotifyBay Frontend JavaScript.
 *
 * Responsibilities (PHP-first architecture):
 * - Variable product variation switching (waitlist/wishlist form swap, FOMO per-variant)
 * - Subscription AJAX (waitlist form submit, wishlist button click)
 * - Cache-busting ping for single product pages (nonce refresh, subscription state updates)
 * - Native WooCommerce notice integration
 * - Menu badge updates
 *
 * Non-variable products and archives are fully rendered by PHP.
 * JS only handles interactivity and dynamic updates.
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
            
            // Array to collect all product IDs present on the current page
            const productIds = [];
            
            // Iterate over every NotifyBay root container found on the page
            $('.notifybay-frontend-root').each(function () {
                // Wrap the current element in a jQuery object
                const $root = $(this);
                
                // Extract the product ID from the data attribute and add to our collection
                productIds.push($root.data('product-id'));
                
                // Initialize this specific product instance (handles initial state)
                self.initInstance($root);
            });

            // Attempt to retrieve a remembered guest email from a browser cookie
            const rememberedEmail = this.getCookie('notifybay_guest_email');
            
            // Batch hydrate guest subscriptions if the user is not logged in but has a remembered email
            if (!notifybay_vars.user.is_logged_in && rememberedEmail && productIds.length > 0) {
                // Remove any duplicate IDs from the array before sending to the server
                const uniqueIds = [...new Set(productIds)];
                
                // Trigger the batch AJAX request to fetch subscription statuses for all products at once
                this.batchHydrateGuestSubscriptions(uniqueIds, rememberedEmail);
            }
        },

        /**
         * Initialize a specific product instance (single product page only).
         * Handles the initial DOM state when the page first loads.
         * 
         * @param {jQuery} $root The root container for a specific product
         */
        initInstance: function ($root) {
            // Get the type of product (e.g., 'simple' or 'variable')
            const productType = $root.data('product-type');

            // Handle variable products specifically as their state changes dynamically
            if (productType === 'variable') {
                // Check if the parent product itself is already wishlisted by the user
                const isSubscribedWishlist = $root.data('subscribed-wishlist') == '1';
                
                if (isSubscribedWishlist) {
                    // If subscribed, find the wishlist button and mark it as active/disabled
                    $root.find('.notifybay-wishlist-btn')
                        .addClass('active')
                        .prop('disabled', true)
                        .text('Already in Wishlist');
                } else {
                    // Otherwise, keep the wishlist button disabled until a specific variation is chosen
                    $root.find('.notifybay-wishlist-btn').prop('disabled', true);
                }
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
                data: {
                    email: email, // The email to check subscriptions for
                    product_ids: productIds // List of products to check
                },
                success: function (response) {
                    // Ensure the response contains the results object
                    if (response.results) {
                        // Iterate through each product ID returned in the result set
                        Object.keys(response.results).forEach(function (productId) {
                            // Extract the subscription data for this specific product
                            const data = response.results[productId];
                            
                            // Find all root containers matching this product ID on the page
                            const $roots = $('.notifybay-frontend-root[data-product-id="' + productId + '"]');

                            // Update each container found
                            $roots.each(function () {
                                const $root = $(this);
                                
                                // Proceed if variation subscription mapping is present
                                if (data.variation_subscriptions) {
                                    // Store the subscription map in the element's data for later retrieval
                                    $root.data('variation-subscriptions', data.variation_subscriptions);

                                    // Get the product type for this specific root
                                    const productType = $root.data('product-type');

                                    // If it's a simple product, apply states immediately
                                    if (productType !== 'variable') {
                                        // Simple products use index '0' for their status
                                        const subs = data.variation_subscriptions[0] || {};
                                        self.applySubscriptionStates($root, subs.waitlist, subs.wishlist, 'simple');
                                    } else {
                                        // For variable products, check if a variation is currently active
                                        const $vForm = $root.closest('.product').find('.variations_form');
                                        const currentVarId = $vForm.find('input.variation_id').val();
                                        
                                        // If a variation is selected, we need to update its UI state now
                                        if (currentVarId > 0) {
                                            // Get the full variation data from the form
                                            const variationData = $vForm.data('variation_data') || [];
                                            // Find the specific variation object matching the current ID
                                            const variation = variationData.find(v => v.variation_id == currentVarId);
                                            
                                            if (variation) {
                                                // Trigger the variation change logic to refresh UI
                                                self.handleVariationChange($root, variation);
                                            }
                                        }
                                    }
                                }
                            });
                        });
                    }
                }
            });
        },

        /**
         * Update the heart icon counter in the main navigation menu.
         * 
         * @param {number} count The new total active wishlist count for the user
         */
        updateMenuBadge: function (count) {
            // Find the badge element in the menu
            const $badge = $('.notifybay-menu-badge');
            
            if ($badge.length) {
                // Set the badge text to the new count
                $badge.text(count);
                
                if (count > 0) {
                    // Show the badge if there are items in the wishlist
                    $badge.show();
                } else {
                    // Hide the badge if the wishlist is empty
                    $badge.hide();
                }
            }
        },

        /**
         * Update the UI state of buttons based on subscription status.
         * 
         * @param {jQuery} $root       The product container
         * @param {boolean} waitlist    Whether the user is on the waitlist
         * @param {boolean} wishlist    Whether the user is on the wishlist
         * @param {string} productType 'simple', 'variable', etc.
         */
        applySubscriptionStates: function ($root, waitlist, wishlist, productType) {
            // Retrieve global settings from the localized variable
            const settings = notifybay_vars.settings;

            // --- Wishlist UI Handling ---
            if (wishlist) {
                // If subscribed, style the button as 'active' and disable further clicks
                $root.find('.notifybay-wishlist-btn, .notifybay-wishlist-trigger')
                    .addClass('active')
                    .prop('disabled', true)
                    .text('Already in Wishlist');
            } else {
                // If not subscribed, reset the button to default state
                $root.find('.notifybay-wishlist-btn, .notifybay-wishlist-trigger')
                    .removeClass('active')
                    // Keep disabled for variable products until a specific variation is picked
                    .prop('disabled', productType === 'variable')
                    .text(settings.wishlist_btn || 'Add to Wishlist');
            }

            // --- Waitlist UI Handling ---
            // We only apply waitlist states here for non-variable products (simple/external)
            if (waitlist && productType !== 'variable') {
                // Mark waitlist buttons as active and disabled if subscribed
                $root.find('.notifybay-waitlist-btn, .notifybay-waitlist-trigger')
                    .addClass('active')
                    .prop('disabled', true)
                    .text('Already on Waitlist');
            } else if (!waitlist && productType !== 'variable') {
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

            // Use event delegation for all NotifyBay submit and trigger buttons
            document.addEventListener('click', function (e) {
                // Check if the click target or its parent is a NotifyBay submit button
                const target = e.target.closest('.notifybay-submit');
                if (!target) return; // Exit if not a NotifyBay button

                // Stop default browser behavior and event propagation
                e.preventDefault();
                e.stopPropagation();

                // Wrap target in jQuery object
                const $btn = $(target);
                
                // Get remembered guest email if available
                const rememberedEmail = self.getCookie('notifybay_guest_email');
                
                // Handle 'Trigger' buttons (guest initial interaction to show form)
                if ($btn.hasClass('notifybay-waitlist-trigger') || $btn.hasClass('notifybay-wishlist-trigger')) {
                    // Find the relevant wrappers and root
                    const $wrapper = $btn.closest('.notifybay-waitlist-wrapper, .notifybay-wishlist-wrapper');
                    const $root = $btn.closest('.notifybay-frontend-root');
                    
                    // UX Optimization: If guest is recognized, bypass form reveal and submit immediately
                    if (!notifybay_vars.user.is_logged_in && rememberedEmail) {
                        const $form = $btn.closest('form'); // Find the form
                        // Determine if it's a wishlist or waitlist click
                        let type = $btn.hasClass('notifybay-wishlist-trigger') ? 'wishlist' : 'waitlist';
                        if ($root.length && $form.length) {
                            // Submit subscription automatically using remembered email
                            self.submitSubscription($root, $form, type);
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

                // Determine subscription type from the wrapper's data attribute.
                // NOTE: this markup can render inside WooCommerce's own <form class="cart">
                // (e.g. the wishlist button on an in-stock product page), and nested <form>
                // elements are invalid HTML — browsers silently drop the inner <form> tag,
                // so $form.hasClass('notifybay-wishlist-form') can never match in that case.
                // The wrapper <div> survives nesting fine, so read type from there instead.
                const $wrapper = $btn.closest('[data-notifybay-type]');
                let type = $wrapper.length ? $wrapper.data('notifybay-type') : 'waitlist';
                if (type !== 'wishlist' && ($form.hasClass('notifybay-wishlist-form') || $btn.hasClass('notifybay-wishlist-btn'))) {
                    type = 'wishlist';
                }

                // If both root and form are present, proceed to submission
                if ($root.length && $form.length) {
                    self.submitSubscription($root, $form, type);
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
            // Reference self for method calls
            const self = this;
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

            // Update the FOMO banner with the count for this specific variation
            this.updateVariantFomo($root, variation.variation_id);

            // --- Scenario A: Out of Stock (Show Waitlist) ---
            if (showWaitlist) {
                // Hide the default cart button
                $cartBtn.hide();
                // Clean up any existing waitlist forms
                $root.find('.notifybay-waitlist-wrapper').remove();
                // Dynamically render the new waitlist form for this variation
                this.renderWaitlistForm($root, variation.variation_id);
                // Hide the wishlist section
                $root.find('.notifybay-wishlist-wrapper').hide();

                // Check pre-hydrated cache to see if user is already on the waitlist for this variant
                const variationSubscriptions = $root.data('variation-subscriptions') || {};
                const subStatus = variationSubscriptions[variation.variation_id] || {};
                // Apply the button states based on subscription status
                this.applySubscriptionStates($root, subStatus.waitlist, subStatus.wishlist, 'simple'); 
            } 
            // --- Scenario B: In Stock (Show Wishlist) ---
            else {
                if (variation.is_purchasable) {
                    // Show the standard WooCommerce cart button
                    $cartBtn.show();
                    // Remove any waitlist forms
                    $root.find('.notifybay-waitlist-wrapper').remove();
                    // Reveal the wishlist section
                    $root.find('.notifybay-wishlist-wrapper').show();
                    // Enable the wishlist button for this variation
                    $root.find('.notifybay-wishlist-btn, .notifybay-wishlist-trigger').prop('disabled', false);

                    // Check pre-hydrated cache for wishlist status
                    const variationSubscriptions = $root.data('variation-subscriptions') || {};
                    const subStatus = variationSubscriptions[variation.variation_id] || {};
                    // Apply UI states
                    this.applySubscriptionStates($root, subStatus.waitlist, subStatus.wishlist, 'simple');
                } 
                // --- Scenario C: Variation Unavailable (Hide All) ---
                else {
                    $cartBtn.hide();
                    $root.find('.notifybay-waitlist-wrapper').remove();
                    $root.find('.notifybay-wishlist-wrapper').hide();
                }
            }
        },

        /**
         * Reset UI when variation selection is cleared.
         * 
         * @param {jQuery} $root The product container
         */
        handleVariationReset: function ($root) {
            // Find the WooCommerce cart button
            const $cartBtn = $root.closest('.product').find('.single_add_to_cart_button');
            // Ensure cart button is visible
            $cartBtn.show();
            // Clear out variation-specific waitlist forms
            $root.find('.notifybay-waitlist-wrapper').remove();
            // Show the wishlist wrapper
            $root.find('.notifybay-wishlist-wrapper').show();
            // Disable wishlist until a new selection is made
            $root.find('.notifybay-wishlist-btn, .notifybay-wishlist-trigger').prop('disabled', true);

            // Restore the total parent FOMO count (clears variant-specific FOMO)
            const $fomoContainer = $root.closest('.product').find('.notifybay-fomo-container');
            if ($fomoContainer.length && notifybay_vars.settings.fomo_enabled) {
                const totalFomo = parseInt($fomoContainer.data('fomo-total')) || 0;
                $fomoContainer.find('.notifybay-fomo').remove(); // Clear old fomo
                // If count is above threshold, render the total fomo message
                if (totalFomo >= notifybay_vars.settings.fomo_threshold) {
                    this.renderFomo($fomoContainer, totalFomo);
                }
            }
        },

        /**
         * Update FOMO banner with per-variant subscriber count.
         *
         * @param {jQuery} $root       The product container
         * @param {int}    variationId The selected variation ID
         */
        updateVariantFomo: function ($root, variationId) {
            // Reference self
            const self = this;
            // Find the FOMO container in the product layout
            const $fomoContainer = $root.closest('.product').find('.notifybay-fomo-container');

            // Exit if FOMO is disabled or container doesn't exist
            if (!$fomoContainer.length || !notifybay_vars.settings.fomo_enabled) return;

            // Retrieve variation-specific FOMO counts from data attribute
            const variationFomoData = $fomoContainer.data('variation-fomo') || {};
            // Get the count for the specific variation, default to 0
            const count = variationFomoData[variationId] !== undefined ? parseInt(variationFomoData[variationId]) : 0;

            // Clear any existing FOMO messages
            $fomoContainer.find('.notifybay-fomo').remove();
            // If count meets threshold, render the new FOMO message
            if (count >= notifybay_vars.settings.fomo_threshold) {
                self.renderFomo($fomoContainer, count);
            }
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
                <div class="notifybay-waitlist-wrapper">
                    <form class="notifybay-waitlist-form" onsubmit="return false;">
                        ${formContent}
                        <input type="hidden" name="variation_id" value="${variationId}">
                    </form>
                </div>
            `;
            // Append the generated form to the product container
            $root.append(formHtml);
        },

        /**
         * Render the FOMO message banner.
         * 
         * @param {jQuery} $container The FOMO container element
         * @param {int}    count      Number of subscribers to display
         */
        renderFomo: function ($container, count) {
            // Replace placeholder in settings template with the actual count
            const msg = notifybay_vars.settings.fomo_template.replace('{count}', count);
            // Check if FOMO message already exists to avoid duplicates
            if (!$container.find('.notifybay-fomo').length) {
                // Prepend the new FOMO banner to the container
                $container.prepend(`<div class="notifybay-fomo">${msg}</div>`);
            }
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
         * Submit a subscription request via AJAX.
         * 
         * @param {jQuery} $root    Product root container
         * @param {jQuery} $element The form or clicked element
         * @param {string} type     'waitlist' or 'wishlist'
         */
        submitSubscription: function ($root, $element, type) {
            // Store self reference
            const self = this;
            // Get product ID from root
            const productId = $root.data('product-id');
            
            let email = ''; // User email
            let variationId = 0; // Variation ID (0 if simple)
            let expiry = 0; // Expiry days
            let targetPrice = null; // Price drop target (future feature)

            if (!$root.length) return; // Exit if invalid root

            // Get remembered guest email from cookie
            const rememberedEmail = this.getCookie('notifybay_guest_email');

            // Extraction Logic for waitlist/wishlist forms
            if (type === 'waitlist' || type === 'wishlist') {
                // Try to get email from input field
                email = $element.find('input[name="notifybay_email"]').val();
                
                // Fallback to cookie if guest input is empty
                if (!email && !notifybay_vars.user.is_logged_in && rememberedEmail) {
                    email = rememberedEmail;
                }
                
                // Extract variation ID from form or WooCommerce global hidden input
                variationId = $element.find('input[name="variation_id"]').val() || $root.closest('.product').find('.variation_id').val() || 0;
                // Extract expiry selection
                expiry = $element.find('select[name="notifybay_expiry"]').val() || 0;
            }

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

                    // --- UI State Updates after success ---
                    if (type === 'waitlist') {
                        // Mark waitlist as subscribed
                        $btn.text('Already on Waitlist').prop('disabled', true).addClass('active');
                        // Hide input fields and description
                        $element.find('.notifybay-waitlist-desc, .notifybay-form-fields').hide();
                    } else if (type === 'wishlist') {
                        // Mark wishlist as subscribed
                        $btn.text('Already in Wishlist').prop('disabled', true).addClass('active');
                        // Hide form
                        $element.find('.notifybay-guest-form-wrapper').hide();
                    }

                    // Sync state of trigger buttons (especially for guests)
                    const $trigger = $element.find('.notifybay-waitlist-trigger, .notifybay-wishlist-trigger');
                    if ($trigger.length) {
                        $trigger.text(type === 'waitlist' ? 'Already on Waitlist' : 'Already in Wishlist')
                                .prop('disabled', true)
                                .addClass('active');
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

                    // If wishlist count was returned in response, sync the menu badge
                    if (response.wishlist_count !== undefined) {
                        self.updateMenuBadge(response.wishlist_count);
                    }
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

                    // Sync nav badge if updated count provided
                    if (response.wishlist_count !== undefined) {
                        self.updateMenuBadge(response.wishlist_count);
                    }

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
 *    [Batch API Returns] --> Update pre-hydration cache (`data('variation-subscriptions')`).
 *     |--> Is it a simple product? 
 *          |--> Yes: Call applySubscriptionStates() immediately.
 *          |--> No: (Variable product) Wait for the user to select a variation.
 * 
 * 3. VARIATION CHANGE FLOW
 *    [User Selects Variation] --> Trigger 'found_variation' event
 *     |--> Update FOMO count for the newly selected variation.
 *     |--> Check Variation Stock Status & Backorder Settings:
 *          |--> Scenario A: Out of Stock (and Waitlist active)
 *               - Hide \"Add to Cart\" button.
 *               - Render dynamic Waitlist Form.
 *               - Hide Wishlist wrapper.
 *               - Pull cached subscription state and update buttons (e.g., \"Already on Waitlist\").
 *          |--> Scenario B: In Stock (Purchasable)
 *               - Show \"Add to Cart\" button.
 *               - Remove Waitlist Form.
 *               - Show Wishlist wrapper.
 *               - Pull cached subscription state and update buttons (e.g., \"Already in Wishlist\" or \"Add to Wishlist\").
 *          |--> Scenario C: Unavailable
 *               - Hide everything.
 * 
 * 4. BUTTON CLICK (SUBSCRIPTION) FLOW
 *    [User Clicks \"Notify Me\" or \"Add to Wishlist\"]
 *     |--> Is it a \"Trigger\" button (guest initial interaction)?
 *          |--> Does the guest have a stored email cookie?
 *               |--> Yes: BYPASS form reveal, auto-submit AJAX instantly.
 *               |--> No: Reveal email input form (slide down).
 *     |--> Is it a \"Submit\" button?
 *          |--> Extract email (from input or cookie fallback).
 *          |--> Send POST /subscribe AJAX.
 *          |--> On Success: Update UI text to \"Already...\", hide form inputs, set guest cookie, update local cache.
 * ============================================================================
 */
