<?php
/**
 * Template: Verification Email.
 *
 * Sent to users who opt in with double opt-in enabled.
 *
 * @var string $verify_url The verification URL with token.
 *
 * @package NotifyBay
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p><?php esc_html_e( 'Please click the link below to verify your subscription:', 'notifybay-waitlist-and-stock-alert-woo' ); ?></p>
<p>
	<a href="<?php echo esc_url( $verify_url ); ?>"><?php esc_html_e( 'Verify Subscription', 'notifybay-waitlist-and-stock-alert-woo' ); ?></a>
</p>
