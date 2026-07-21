<?php
/**
 * Back-in-stock (restock) notification email.
 *
 * Sent to waitlist subscribers when an out-of-stock product is restocked.
 * Rendering only — dispatch is owned by Engine\Worker (event `waitlist_restock`).
 *
 * @package    NotifyBay
 * @subpackage Emails
 * @since      1.1.0
 */

namespace NotifyBay\Emails;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WC_Email' ) ) {
	return;
}

/**
 * Class RestockEmail
 */
class RestockEmail extends NotifyBayEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id            = 'notifybay_restock';
		$this->title         = __( 'NotifyBay: Back in stock', 'notifybay-waitlist-and-stock-alert-woo' );
		$this->description   = __( 'Sent to waitlist subscribers when a product they were waiting for is restocked.', 'notifybay-waitlist-and-stock-alert-woo' );
		$this->template_html = 'emails/wc/restock.php';

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Good news! {product_name} is back in stock', 'notifybay-waitlist-and-stock-alert-woo' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Back in stock!', 'notifybay-waitlist-and-stock-alert-woo' );
	}
}
