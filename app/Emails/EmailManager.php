<?php
/**
 * Registers NotifyBay's WooCommerce email classes and provides lookup.
 *
 * Free registers its own emails (restock, verification) here. NotifyBay Pro
 * hooks the same `woocommerce_email_classes` filter separately to add its
 * premium emails (price-drop, hurry) against the shared NotifyBayEmail base.
 *
 * @package    NotifyBay
 * @subpackage Emails
 * @since      1.1.0
 */

namespace NotifyBay\Emails;

use NotifyBay\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailManager
 */
class EmailManager {

	/**
	 * The single instance of the class.
	 *
	 * @var EmailManager
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return EmailManager
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
		$loader->add_filter( 'woocommerce_email_classes', $this, 'register_emails' );
		// One-time migration of legacy subject/body settings into the native WC
		// email options. Runs early on `init` (front + admin) so a background
		// Action Scheduler send picks up migrated customizations even before the
		// merchant next opens wp-admin.
		$loader->add_action( 'init', $this, 'maybe_migrate_settings', 5 );

		// Preview + test-send controls (shared by all NotifyBay emails, incl. Pro).
		$loader->add_action( 'admin_post_notifybay_email_preview', $this, 'handle_preview' );
		$loader->add_action( 'admin_post_notifybay_email_test', $this, 'handle_test' );
		$loader->add_action( 'admin_notices', $this, 'maybe_test_sent_notice' );
	}

	/**
	 * Sample render context for preview / test emails.
	 *
	 * @return array
	 */
	public static function sample_context() {
		return array(
			'product_name'          => __( 'Sample Product', 'notifybay' ),
			'original_product_name' => __( 'Sample Product', 'notifybay' ),
			'customer_name'         => __( 'Jane Doe', 'notifybay' ),
			'customer_first_name'   => __( 'Jane', 'notifybay' ),
			'stock_qty'             => 3,
			'buy_link'              => home_url( '/?p=1' ),
			'unsubscribe_url'       => home_url( '/?notifybay_action=unsubscribe&token=sample' ),
			'verify_url'            => home_url( '/?notifybay_action=verify&token=sample' ),
		);
	}

	/**
	 * Resolve, authorize, and hydrate the requested email for a preview/test.
	 *
	 * @param string $nonce_action The expected nonce action prefix.
	 * @return NotifyBayEmail
	 */
	private function authorize_email_request( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'notifybay' ) );
		}

		$email_id = isset( $_GET['email_id'] ) ? sanitize_text_field( wp_unslash( $_GET['email_id'] ) ) : '';
		check_admin_referer( $nonce_action . '_' . $email_id );

		$email = self::get_email( $email_id );
		if ( ! $email ) {
			wp_die( esc_html__( 'Email not found.', 'notifybay' ) );
		}

		$email->set_context( null, self::sample_context() );
		return $email;
	}

	/**
	 * admin-post handler: output the rendered email HTML for previewing.
	 *
	 * @return void
	 */
	public function handle_preview() {
		$email = $this->authorize_email_request( 'notifybay_email_preview' );

		header( 'Content-Type: text/html; charset=utf-8' );
		// Rendered, CSS-inlined email HTML — intentionally output verbatim.
		echo $email->render_final_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * admin-post handler: send a test copy of the email to the site admin.
	 *
	 * @return void
	 */
	public function handle_test() {
		$email = $this->authorize_email_request( 'notifybay_email_test' );

		$to      = get_option( 'admin_email' );
		$subject = '[' . __( 'Test', 'notifybay' ) . '] ' . $email->get_subject();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $email->render_final_html(), $headers );

		$referer = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'notifybay_test_sent', '1', $referer ? $referer : admin_url( 'admin.php?page=wc-settings&tab=email' ) ) );
		exit;
	}

	/**
	 * Admin notice confirming a test email was sent.
	 *
	 * @return void
	 */
	public function maybe_test_sent_notice() {
		if ( empty( $_GET['notifybay_test_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: admin email address */
					esc_html__( 'NotifyBay test email sent to %s.', 'notifybay' ),
					esc_html( get_option( 'admin_email' ) )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Migrate legacy `email_restock*` / `email_verification*` subject+body
	 * settings into the native `woocommerce_<id>_settings` options, once.
	 *
	 * Reads the RAW stored option (not Settings::get_settings(), which no longer
	 * exposes these now-removed schema keys) and removes the old keys afterward.
	 *
	 * @return void
	 */
	public function maybe_migrate_settings() {
		if ( 'yes' === get_option( 'notifybay_wc_email_migrated' ) ) {
			return;
		}

		$raw = get_option( \NOTIFYBAY_OPTION_NAME );

		if ( is_array( $raw ) ) {
			self::migrate_email_option( 'notifybay_restock', $raw, 'email_restockSubject', 'email_restockBody' );
			self::migrate_email_option( 'notifybay_verification', $raw, 'email_verificationSubject', 'email_verificationBody' );

			unset(
				$raw['email_restockSubject'],
				$raw['email_restockBody'],
				$raw['email_verificationSubject'],
				$raw['email_verificationBody']
			);
			update_option( \NOTIFYBAY_OPTION_NAME, $raw );
		}

		update_option( 'notifybay_wc_email_migrated', 'yes' );
	}

	/**
	 * Copy one email's legacy subject/body into its WC email settings option.
	 * The legacy full-body value maps onto WC's `additional_content` field
	 * (shown below the standard template body). Never clobbers an existing
	 * WC-side value.
	 *
	 * @param string $wc_id       The WC_Email id (e.g. `notifybay_restock`).
	 * @param array  $raw         The raw stored NotifyBay option.
	 * @param string $subject_key Legacy subject key.
	 * @param string $body_key    Legacy body key.
	 * @return void
	 */
	public static function migrate_email_option( $wc_id, $raw, $subject_key, $body_key ) {
		$option   = 'woocommerce_' . $wc_id . '_settings';
		$existing = get_option( $option, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		if ( ! empty( $raw[ $subject_key ] ) && empty( $existing['subject'] ) ) {
			$existing['subject'] = $raw[ $subject_key ];
		}
		if ( ! empty( $raw[ $body_key ] ) && empty( $existing['additional_content'] ) ) {
			$existing['additional_content'] = $raw[ $body_key ];
		}

		update_option( $option, $existing );
	}

	/**
	 * Add NotifyBay's core email classes to WooCommerce's registry.
	 *
	 * @param array $emails Registered WC_Email instances keyed by class name.
	 * @return array
	 */
	public function register_emails( $emails ) {
		$emails['NotifyBay_Restock']      = new RestockEmail();
		$emails['NotifyBay_Verification'] = new VerificationEmail();
		return $emails;
	}

	/**
	 * Look up a registered NotifyBay email instance by its WC email id
	 * (e.g. `notifybay_restock`). Used by Engine\Worker at send time.
	 *
	 * @param string $id The WC_Email id.
	 * @return NotifyBayEmail|null
	 */
	public static function get_email( $id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof NotifyBayEmail && $email->id === $id ) {
				return $email;
			}
		}

		return null;
	}
}
