<?php
/**
 * Admin WooCommerce Integration.
 *
 * Handles custom columns and product-level overrides.
 *
 * @package    NotifyBay
 * @subpackage Admin
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Admin;

use NotifyBay\Core\Plugin;
use NotifyBay\Helper\TemplateRenderer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WooCommerce
 */
class WooCommerce {

	/**
	 * The single instance of the class.
	 *
	 * @var WooCommerce
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WooCommerce
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

		// Product list columns
		$loader->add_filter( 'manage_edit-product_columns', $this, 'add_product_columns' );
		$loader->add_action( 'manage_product_posts_custom_column', $this, 'render_product_columns', 10, 2 );

		// Meta box for overrides
		$loader->add_action( 'add_meta_boxes', $this, 'add_override_meta_box' );
		$loader->add_action( 'save_post_product', $this, 'save_override_meta_box' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'inject_column_styles' );
	}

	/**
	 * Add custom columns to product list.
	 *
	 * @param array $columns The columns.
	 * @return array
	 */
	public function add_product_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'name' === $key ) {
				$new_columns['notifybay_waitlist'] = __( 'Waitlist', 'notifybay-waitlist-and-stock-alert-woo' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_product_columns( $column, $post_id ) {
		if ( 'notifybay_waitlist' !== $column ) {
			return;
		}

		global $wpdb;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom {$wpdb->prefix}notifybay_leads table; values are bound via prepare(). Direct, uncached queries are intentional for this real-time data-access layer.
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}notifybay_leads WHERE product_id = %d AND status = 'active' AND type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$post_id,
				'waitlist'
			)
		);

		TemplateRenderer::render(
			'admin/product-column-leads',
			array(
				'count'   => $count,
				'post_id' => $post_id,
				'type'    => 'waitlist',
			)
		);
	}

	/**
	 * Inject CSS to fix column widths on product list.
	 */
	public function inject_column_styles() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		$css = '.fixed .column-notifybay_waitlist{width:85px;text-align:center}'
			. '@media screen and (max-width:782px){.fixed .column-notifybay_waitlist{width:auto}}';

		wp_register_style( 'notifybay-admin-columns', false, array(), \NOTIFYBAY_VERSION );
		wp_enqueue_style( 'notifybay-admin-columns' );
		wp_add_inline_style( 'notifybay-admin-columns', $css );
	}

	/**
	 * Add override meta box.
	 */
	public function add_override_meta_box() {
		add_meta_box(
			'notifybay_overrides',
			__( 'NotifyBay Settings', 'notifybay-waitlist-and-stock-alert-woo' ),
			array( $this, 'render_override_meta_box' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public function render_override_meta_box( $post ) {
		$overrides = get_post_meta( $post->ID, '_notifybay_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}
		wp_nonce_field( 'notifybay_save_overrides', 'notifybay_overrides_nonce' );

		TemplateRenderer::render(
			'admin/meta-box-overrides',
			array( 'overrides' => $overrides )
		);
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_override_meta_box( $post_id ) {
		if ( ! isset( $_POST['notifybay_overrides_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['notifybay_overrides_nonce'] ) ), 'notifybay_save_overrides' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$overrides = array(
			'disable_waitlist'  => isset( $_POST['notifybay_disable_waitlist'] ),
			'smart_transition'  => sanitize_text_field( wp_unslash( $_POST['notifybay_smart_transition'] ?? 'default' ) ),
			'backorder_mode'    => sanitize_text_field( wp_unslash( $_POST['notifybay_backorder_mode'] ?? 'default' ) ),
			'max_waitlist_size' => isset( $_POST['notifybay_max_waitlist_size'] ) ? (int) wp_unslash( $_POST['notifybay_max_waitlist_size'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- value is cast to (int); the isset() presence checks above need no sanitization. Nonce/capability verified at the top of this handler.
		);

		/**
		 * Filters the per-product override values saved from the NotifyBay meta box.
		 *
		 * Lets an add-on (NotifyBay Pro) persist its own override fields that Free
		 * does not own. The nonce and capability checks at the top of this handler
		 * already gate the save.
		 *
		 * @since 1.0.0
		 * @param array $overrides The override values collected from $_POST.
		 * @param int   $post_id   The product being saved.
		 */
		$overrides = apply_filters( 'notifybay_save_product_overrides', $overrides, $post_id );

		update_post_meta( $post_id, '_notifybay_overrides', $overrides );
	}
}
