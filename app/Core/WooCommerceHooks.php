<?php
/**
 * WooCommerce Hooks Observer.
 *
 * Connects WooCommerce lifecycle events to the NotifyBay engine.
 *
 * @package    NotifyBay
 * @subpackage Core
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Core;

use NotifyBay\Models\Lead;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerceHooks
 */
class WooCommerceHooks {


	/**
	 * The single instance of the class.
	 *
	 * @var WooCommerceHooks
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WooCommerceHooks
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();

		// Stock Observers. Two WooCommerce hooks matter here and they are not
		// interchangeable: set_stock fires only when stock_quantity is among
		// the updated props, while flipping the In stock / Out of stock
		// dropdown on a product that does not manage quantities fires only
		// set_stock_status. Listening to the first alone meant merchants who
		// do not track quantities collected waitlist leads that were never
		// notified -- the signup form still shows, so the leads pile up unused.
		// A quantity change updates both props and fires both hooks, so
		// maybe_enqueue_restock() de-duplicates within the request.
		$loader->add_action( 'woocommerce_product_set_stock', $this, 'handle_stock_change' );
		$loader->add_action( 'woocommerce_variation_set_stock', $this, 'handle_stock_change' );
		$loader->add_action( 'woocommerce_product_set_stock_status', $this, 'handle_stock_status_change', 10, 3 );
		$loader->add_action( 'woocommerce_variation_set_stock_status', $this, 'handle_stock_status_change', 10, 3 );

		// Note: additional stock/order observers and conversion tracking are
		// provided by a premium add-on (NotifyBay Pro), which registers them
		// directly, not here.

		// Data Integrity
		$loader->add_action( 'before_delete_post', $this, 'handle_product_deletion' );
		$loader->add_action( 'woocommerce_before_delete_product_variation', $this, 'handle_variation_deletion' );
		$loader->add_action( 'transition_post_status', $this, 'handle_status_transition', 10, 3 );
		$loader->add_action( 'woocommerce_product_set_catalog_visibility', $this, 'handle_visibility_change', 10, 2 );

		// Customer Merge
		$loader->add_action( 'woocommerce_created_customer', $this, 'merge_guest_leads' );
	}

	/**
	 * Handle stock changes.
	 *
	 * @param \WC_Product $product The product object.
	 */
	public function handle_stock_change( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->maybe_enqueue_restock( $product );
	}

	/**
	 * Handle a stock *status* change -- the only restock signal a product that
	 * does not manage quantities ever emits.
	 *
	 * The signature is WooCommerce's, not ours: it passes the id and the new
	 * status before the product object, so this cannot share a callback with
	 * handle_stock_change().
	 *
	 * @param int    $product_id   Product or variation id.
	 * @param string $stock_status The new stock status. Unused; is_in_stock() is authoritative.
	 * @param mixed  $product      The product object when WooCommerce supplies one.
	 */
	public function handle_stock_status_change( $product_id, $stock_status = '', $product = null ) {
		if ( ! $product instanceof \WC_Product ) {
			$product = wc_get_product( $product_id );
		}

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->maybe_enqueue_restock( $product );
	}

	/**
	 * Queue a restock dispatch if the product is now buyable.
	 *
	 * @param \WC_Product $product The product or variation.
	 */
	private function maybe_enqueue_restock( \WC_Product $product ) {
		$product_id   = $product->get_id();
		$variation_id = $product->is_type( 'variation' ) ? $product_id : 0;
		$parent_id    = $variation_id ? $product->get_parent_id() : $product_id;

		$stock_quantity = $product->get_stock_quantity();

		// null means the product does not manage stock, so there is no
		// quantity to satisfy and is_in_stock() is the whole answer. The old
		// `$stock_quantity > 0` test read that null as zero and refused to
		// notify anyone waiting on an unmanaged product.
		$has_quantity = ( null === $stock_quantity ) || ( $stock_quantity > 0 );

		/*
		 * `onbackorder` is not a restock. is_in_stock() is
		 * `'outofstock' !== get_stock_status()`, so a backorder-able product
		 * passes it, and once $has_quantity stopped reading an unmanaged
		 * product's NULL quantity as zero, a merchant flipping an unmanaged
		 * product to "On backorder" mailed the entire waitlist to say it was
		 * back. It is not back -- that is what backorder means -- and the email
		 * states it plainly.
		 *
		 * Tested on top of is_in_stock() rather than in place of it, so the
		 * `woocommerce_product_is_in_stock` filter still governs the normal
		 * path and only this one status is carved out. The managed case was
		 * already covered by the quantity test; this closes the unmanaged one.
		 *
		 * Independent of general_backorderWaitlist, which decides whether a
		 * backorder-able product is offered a waitlist at all
		 * (Frontend\ProductPage::should_show_waitlist) -- not whether reaching
		 * backorder counts as being restocked. It never does.
		 */
		if ( ! $product->is_in_stock() || 'onbackorder' === $product->get_stock_status() || ! $has_quantity ) {
			return;
		}

		// A quantity change fires set_stock AND set_stock_status, so without
		// this the managed path would enqueue twice and the customer would get
		// two emails. as_enqueue_async_action() de-duplicates only while an
		// identical action is still pending, which is not guaranteed here.
		static $enqueued = array();
		$key             = $parent_id . ':' . $variation_id;

		if ( isset( $enqueued[ $key ] ) ) {
			return;
		}

		$enqueued[ $key ] = true;

		/*
		 * Only queue a dispatch for a product somebody is actually waiting on.
		 * Every in-stock product reaching this point used to get an Action
		 * Scheduler job regardless, and since an unmanaged product now satisfies
		 * $has_quantity, that is most of a catalogue: a bulk stock write or a CSV
		 * import enqueued one job per product, each of which woke a worker only to
		 * find no leads and exit. The de-dup above is keyed per product, so it does
		 * not help across thousands of distinct ones.
		 *
		 * Mirrors Engine\Dispatcher's own selection predicate, and is covered by
		 * idx_product_variation_status.
		 */
		if ( ! Lead::has_active_waitlist( $parent_id, $variation_id ) ) {
			// The cache flush is deliberately left outside the guard: the product's
			// stock genuinely changed, so its transients are stale whether or not
			// NotifyBay has anyone to email about it.
			wc_delete_product_transients( $parent_id );
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'notifybay_run_dispatcher', array( $parent_id, $variation_id ), 'notifybay_alerts' );
		}

		wc_delete_product_transients( $parent_id );
	}

	/**
	 * Handle product deletion.
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_product_deletion( $post_id ) {
		if ( get_post_type( $post_id ) !== 'product' ) {
			return;
		}

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$wpdb->delete( $table, array( 'product_id' => $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
	}

	/**
	 * Handle variation deletion.
	 *
	 * @param int $variation_id Variation ID.
	 */
	public function handle_variation_deletion( $variation_id ) {
		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$wpdb->delete( $table, array( 'variation_id' => $variation_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
	}

	/**
	 * Handle status transitions (Trash/Hidden).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 */
	public function handle_status_transition( $new_status, $old_status, $post ) {
		if ( 'product' !== $post->post_type ) {
			return;
		}

		if ( 'trash' === $new_status ) {
			global $wpdb;
			$lead_model = new Lead();
			$table      = $lead_model->get_table();

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$table,
				array(
					'status'     => 'expired',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'product_id' => $post->ID,
					'status'     => 'active',
				)
			);
		}
	}

	/**
	 * Handle visibility changes.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $visibility Visibility status.
	 */
	public function handle_visibility_change( $product_id, $visibility ) {
		if ( 'hidden' === $visibility ) {
			global $wpdb;
			$lead_model = new Lead();
			$table      = $lead_model->get_table();

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
				$table,
				array(
					'status'     => 'expired',
					'updated_at' => current_time( 'mysql' ),
				),
				array(
					'product_id' => $product_id,
					'status'     => 'active',
				)
			);
		}
	}

	/**
	 * Merge guest leads to new customer.
	 *
	 * @param int $customer_id Customer ID.
	 */
	public function merge_guest_leads( $customer_id ) {
		$user = get_user_by( 'id', $customer_id );
		if ( ! $user ) {
			return;
		}

		global $wpdb;
		$lead_model = new Lead();
		$table      = $lead_model->get_table();

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$table,
			array(
				'user_id'    => $customer_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'user_email' => $user->user_email,
				'user_id'    => null,
			)
		);
	}
}
