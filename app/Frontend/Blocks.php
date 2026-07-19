<?php
/**
 * Gutenberg Blocks Integration.
 *
 * Registers NotifyBay blocks that wrap our shortcodes.
 *
 * @package    NotifyBay
 * @subpackage Frontend
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Frontend;

use NotifyBay\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks
 */
class Blocks {

	/**
	 * The single instance of the class.
	 *
	 * @var Blocks
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return Blocks
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
		$loader->add_action( 'init', $this, 'register_blocks' );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets' );
	}

	/**
	 * Enqueue Gutenberg block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		$deps_file  = \NOTIFYBAY_PATH . 'build/blocks.asset.php';
		$dependency = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' );
		$version    = \NOTIFYBAY_VERSION;
		if ( file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		wp_enqueue_script(
			'notifybay-blocks',
			\NOTIFYBAY_URL . 'build/blocks.js',
			$dependency,
			$version,
			true
		);
	}

	/**
	 * Register Gutenberg blocks.
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register Waitlist Block
		register_block_type(
			'notifybay/waitlist',
			array(
				'render_callback' => array( $this, 'render_waitlist_block' ),
				'attributes'      => array(
					'productId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		// Register Wishlist Block
		register_block_type(
			'notifybay/wishlist',
			array(
				'render_callback' => array( $this, 'render_wishlist_block' ),
				'attributes'      => array(
					'productId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Render waitlist block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_waitlist_block( $attributes ) {
		$id = ! empty( $attributes['productId'] ) ? $attributes['productId'] : 0;
		return do_shortcode( '[notifybay_waitlist id="' . esc_attr( $id ) . '"]' );
	}

	/**
	 * Render wishlist block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_wishlist_block( $attributes ) {
		$id = ! empty( $attributes['productId'] ) ? $attributes['productId'] : 0;
		return do_shortcode( '[notifybay_wishlist id="' . esc_attr( $id ) . '"]' );
	}
}
