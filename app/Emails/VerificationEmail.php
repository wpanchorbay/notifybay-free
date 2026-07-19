<?php
/**
 * Subscription verification (double opt-in) email.
 *
 * Sent to ask a subscriber to confirm their subscription. Rendering only —
 * dispatch is owned by Engine\Worker (event `verification`).
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
 * Class VerificationEmail
 */
class VerificationEmail extends NotifyBayEmail {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id            = 'notifybay_verification';
		$this->title         = __( 'NotifyBay: Confirm subscription', 'notifybay' );
		$this->description    = __( 'Double opt-in email asking a subscriber to confirm their subscription.', 'notifybay' );
		$this->template_html = 'emails/wc/verification.php';

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Verify your subscription to {product_name}', 'notifybay' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Confirm your subscription', 'notifybay' );
	}
}
