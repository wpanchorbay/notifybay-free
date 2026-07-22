<?php
/**
 * The settings functionality of the plugin.
 *
 * @package    NotifyBay
 * @subpackage Core
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */

namespace NotifyBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings functionality of the plugin.
 *
 * @since      1.0.0
 * @package    NotifyBay
 * @subpackage NotifyBay/Core
 * @author     WPAnchorBay <sankarsan@wpanchorbay.com>
 */
class Settings {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var   Settings
	 */
	private static $instance = null;

	/**
	 * Settings configuration.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var   array
	 */
	private $config = null;

	/**
	 * The settings of the plugin.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var   array
	 */
	private $settings = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Configuration is lazy-loaded via get_config()
	}

	/**
	 * Get Settings configuration (Lazy Loaded).
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_config() {
		if ( null === $this->config ) {
			$this->config = include \NOTIFYBAY_PATH . 'config/settings.php';
		}
		return $this->config;
	}

	/**
	 * Gets an instance of this object.
	 *
	 * @access public
	 * @return Settings
	 * @since 1.0.0
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get the settings with caching.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $key           optional meta key.
	 * @param mixed  $default_value optional fallback returned when the key is not set. Only used when $key is non-empty.
	 * @return array|mixed|null
	 */
	public function get_settings( $key = '', $default_value = false ) {
		if ( ! $this->settings ) {
			$this->load_settings();
		}
		if ( ! empty( $key ) ) {
			return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default_value;
		}
		return $this->settings;
	}

	/**
	 * Get the default settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array
	 */
	public function get_default_settings() {
		/**
		 * Filters the default settings values for the plugin.
		 *
		 * Allows an add-on (e.g. NotifyBay Pro) to register default values for
		 * settings keys it introduces via the `notifybay_options_properties` schema filter.
		 *
		 * @since 1.0.0
		 * @hook notifybay_options_defaults
		 * @param array $defaults The associative array of default setting values.
		 * @return array The filtered array of default setting values.
		 */
		return apply_filters(
			'notifybay_options_defaults',
			$this->get_config()['defaults'] ?? array()
		);
	}

	/**
	 * Load the settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function load_settings() {
		$options = get_option( \NOTIFYBAY_OPTION_NAME );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$default_settings = $this->get_default_settings();
		// Only honor stored values for keys currently registered in the schema
		// (Free's own + whatever an active add-on has filtered in). This keeps
		// orphaned keys — e.g. Pro-only settings left over after Pro is
		// deactivated/deleted, or a license_key from a prior install — from
		// leaking into Free-alone behavior via a stale stored option.
		$settings       = array_merge( $default_settings, array_intersect_key( $options, $default_settings ) );
		$this->settings = $settings;
	}

	/**
	 * Update the settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string|array $key_or_data The key or data to update.
	 * @param string       $val         The value to update.
	 * @return void
	 */
	public function update_settings( $key_or_data, $val = '' ) {
		if ( is_string( $key_or_data ) ) {
			$options                 = $this->get_settings();
			$options[ $key_or_data ] = $val;
		} else {
			$options = $key_or_data;
		}
		update_option( \NOTIFYBAY_OPTION_NAME, $options );
		$this->load_settings();
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function register() {
		$defaults = $this->get_default_settings();

		register_setting(
			'notifybay_settings_group',
			\NOTIFYBAY_OPTION_NAME,
			array(
				'type'              => 'object',
				'default'           => $defaults,
				'show_in_rest'      => array(
					'schema' => $this->get_settings_schema(),
				),
				'sanitize_callback' => array( $this, 'sanitize_settings_object' ),
			)
		);
	}

	/**
	 * Get settings schema.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array settings schema for this plugin.
	 */
	public function get_settings_schema() {
		/**
		 * Filters the settings schema for the plugin.
		 *
		 * @since 1.0.0
		 * @hook notifybay_options_properties
		 * @param array $setting_properties The associative array of setting properties.
		 * @return array The filtered array of setting properties.
		 */
		$setting_properties = apply_filters(
			'notifybay_options_properties',
			$this->get_config()['schema'] ?? array()
		);

		return array(
			'type'       => 'object',
			'properties' => $setting_properties,
		);
	}

	/**
	 * Custom sanitization callback for the main settings object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $input The raw array of settings data submitted for saving.
	 * @return array The sanitized array of settings data.
	 */
	public function sanitize_settings_object( $input ) {
		$schema           = $this->get_settings_schema();
		$properties       = $schema['properties'] ?? array();
		$default_options  = $this->get_default_settings();
		$sanitized_output = get_option( \NOTIFYBAY_OPTION_NAME, $default_options );

		foreach ( $properties as $key => $details ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$value = $input[ $key ];
			$type  = $details['type'] ?? 'string';

			switch ( $type ) {
				case 'boolean':
					$sanitized_output[ $key ] = (bool) $value;
					break;
				case 'integer':
					$sanitized_output[ $key ] = absint( $value );
					break;
				case 'string':
					$sanitized_output[ $key ] = sanitize_text_field( $value );
					break;
				default:
					$sanitized_output[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized_output;
	}
	/**
	 * Register the hooks for settings.
	 *
	 * @since    1.0.0
	 * @param    \NotifyBay\Core\Plugin $plugin The Plugin instance.
	 * @return   void
	 */
	public function run( $plugin ) {
		$loader = $plugin->get_loader();
		$loader->add_action( 'rest_api_init', $this, 'register' );
		$loader->add_action( 'admin_init', $this, 'register' );
	}
}
